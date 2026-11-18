<?php

/*
 * Licensed to Elasticsearch B.V. under one or more contributor
 * license agreements. See the NOTICE file distributed with
 * this work for additional information regarding copyright
 * ownership. Elasticsearch B.V. licenses this file to you under
 * the Apache License, Version 2.0 (the "License"); you may
 * not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations
 * under the License.
 */

/** @noinspection PhpComposerExtensionStubsInspection */

declare(strict_types=1);

namespace ElasticApmTests\UnitTests;

use Elastic\Apm\ElasticApm;
use Elastic\Apm\Impl\AutoInstrument\PDOAutoInstrumentation;
use Elastic\Apm\Impl\AutoInstrument\RegistrationContextInterface;
use Elastic\Apm\Impl\Config\OptionNames;
use Elastic\Apm\Impl\Log\Level as LogLevel;
use Elastic\Apm\Impl\Tracer;
use ElasticApmTests\UnitTests\Util\MockLogSink;
use ElasticApmTests\UnitTests\Util\MockLogSinkStatement;
use ElasticApmTests\UnitTests\Util\TracerUnitTestCaseBase;
use ElasticApmTests\UnitTests\Util\UserlandPdoSubclassForTests;
use ElasticApmTests\Util\TracerBuilderForTests;
use PDO;
use PDOStatement;
use ReflectionClass;

/**
 * Drives PDOAutoInstrumentation through the same pre-hook -> post-hook sequence
 * the extension uses for intercepted methods, without connecting to any database.
 *
 * @see https://github.com/elastic/apm-agent-php/issues/1475
 */
final class PDOAutoInstrumentationUnitTest extends TracerUnitTestCaseBase
{
    private const DSN = 'pgsql:host=localhost;port=5432;dbname=test_db';
    private const EXPECTED_DB_TYPE = 'postgresql';
    private const EXPECTED_DB_NAME = 'test_db';
    private const SQL = 'SELECT 1';

    /**
     * Driver specific PDO subclasses added in PHP 8.4
     *
     * @link https://wiki.php.net/rfc/pdo_driver_specific_subclasses
     */
    private const PHP_84_DRIVER_SPECIFIC_SUBCLASSES = ['Pdo\\Dblib', 'Pdo\\Firebird', 'Pdo\\Mysql', 'Pdo\\Odbc', 'Pdo\\Pgsql', 'Pdo\\Sqlite'];

    private const PDO_METHODS_EXPECTED_TO_BE_INTERCEPTED = ['__construct', 'exec', 'query', 'prepare', 'beginTransaction', 'commit', 'rollBack'];

    /** @var MockLogSink */
    private $mockLogSink;

    /** @var array<string, array<string, callable(?object, mixed[]): ?callable>> */
    private $classToMethodToPreHook;

    /** @inheritDoc */
    public function setUp(): void
    {
        parent::setUp();

        if (!extension_loaded('pdo')) {
            self::markTestSkipped('Required extension pdo is not loaded');
        }

        $this->mockLogSink = new MockLogSink();
        $this->setUpTestEnv(
            function (TracerBuilderForTests $builder): void {
                $builder->withLogSink($this->mockLogSink)->withConfig(OptionNames::LOG_LEVEL, 'WARNING');
            }
        );

        $registrationCtx = new class implements RegistrationContextInterface {
            /** @var array<string, array<string, callable(?object, mixed[]): ?callable>> */
            public $classToMethodToPreHook = [];

            public function interceptCallsToInternalMethod(string $className, string $methodName, callable $preHook): void
            {
                TracerUnitTestCaseBase::assertArrayNotHasKey($methodName, $this->classToMethodToPreHook[$className] ?? [], $className . '::' . $methodName);
                $this->classToMethodToPreHook[$className][$methodName] = $preHook;
            }

            public function interceptCallsToInternalFunction(string $functionName, callable $preHook): void
            {
                TracerUnitTestCaseBase::fail('Unexpected call to ' . __FUNCTION__ . ' for ' . $functionName);
            }
        };
        $tracer = $this->tracer;
        self::assertInstanceOf(Tracer::class, $tracer);
        (new PDOAutoInstrumentation($tracer))->register($registrationCtx);
        $this->classToMethodToPreHook = $registrationCtx->classToMethodToPreHook;
    }

    private static function isPdoConnectSupported(): bool
    {
        return PHP_VERSION_ID >= 80400;
    }

    /**
     * @param string  $className
     * @param string  $methodName
     * @param ?object $thisObj
     * @param mixed[] $args
     * @param mixed   $retVal
     */
    private function callIntercepted(string $className, string $methodName, ?object $thisObj, array $args, $retVal): void
    {
        self::assertArrayHasKey($className, $this->classToMethodToPreHook);
        self::assertArrayHasKey($methodName, $this->classToMethodToPreHook[$className], $className);
        $postHook = ($this->classToMethodToPreHook[$className][$methodName])($thisObj, $args);
        if ($postHook !== null) {
            $postHook(/* numberOfStackFramesToSkip */ 0, /* hasExitedByException */ false, $retVal);
        }
    }

    /**
     * Creates an object without connecting to any database
     */
    private static function newPdoWithoutConnecting(string $className): PDO
    {
        self::assertTrue(is_a($className, PDO::class, /* allow_string */ true), $className);
        $pdo = (new ReflectionClass($className))->newInstanceWithoutConstructor();
        self::assertInstanceOf(PDO::class, $pdo);
        return $pdo;
    }

    /**
     * @return string[]
     */
    private function spansDesc(): array
    {
        $result = [];
        foreach ($this->mockEventSink->idToSpan() as $span) {
            self::assertNotNull($span->context);
            self::assertNotNull($span->context->destination);
            self::assertNotNull($span->context->destination->service);
            $result[] = $span->name . ' / ' . $span->subtype . ' / ' . $span->context->destination->service->resource;
        }
        sort($result);
        return $result;
    }

    private function assertNothingLoggedAtWarningOrWorse(): void
    {
        $messages = array_map(
            function (MockLogSinkStatement $statement): string {
                return $statement->message;
            },
            array_filter(
                $this->mockLogSink->consumed,
                function (MockLogSinkStatement $statement): bool {
                    return $statement->statementLevel <= LogLevel::WARNING;
                }
            )
        );
        self::assertSame([], array_values($messages));
    }

    public function testFindInternalPdoSubclasses(): void
    {
        self::assertTrue(class_exists(UserlandPdoSubclassForTests::class));

        $actual = PDOAutoInstrumentation::findInternalPdoSubclasses();

        self::assertNotContains(PDO::class, $actual);
        self::assertNotContains(UserlandPdoSubclassForTests::class, $actual);
        foreach ($actual as $className) {
            self::assertTrue(is_subclass_of($className, PDO::class), $className);
            self::assertTrue((new ReflectionClass($className))->isInternal(), $className);
        }

        if (PHP_VERSION_ID < 80400) {
            self::assertSame([], $actual);
            return;
        }

        foreach (self::PHP_84_DRIVER_SPECIFIC_SUBCLASSES as $className) {
            if (class_exists($className, /* autoload */ false)) {
                self::assertContains($className, $actual);
            }
        }
    }

    public function testAllPdoClassesAreIntercepted(): void
    {
        $expectedMethods = self::PDO_METHODS_EXPECTED_TO_BE_INTERCEPTED;
        if (self::isPdoConnectSupported()) {
            $expectedMethods[] = 'connect';
        }
        sort($expectedMethods);

        $expectedClasses = array_merge([PDO::class], PDOAutoInstrumentation::findInternalPdoSubclasses());
        foreach ($expectedClasses as $className) {
            self::assertArrayHasKey($className, $this->classToMethodToPreHook);
            $actualMethods = array_keys($this->classToMethodToPreHook[$className]);
            sort($actualMethods);
            self::assertSame($expectedMethods, $actualMethods, $className);
        }

        self::assertSame(['execute'], array_keys($this->classToMethodToPreHook[PDOStatement::class]));

        $expectedClasses[] = PDOStatement::class;
        sort($expectedClasses);
        $actualClasses = array_keys($this->classToMethodToPreHook);
        sort($actualClasses);
        self::assertSame($expectedClasses, $actualClasses);
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public function dataProviderForTestDbInfoIsUsedForSpans(): iterable
    {
        if (!extension_loaded('pdo')) {
            // setUp() will skip the test
            yield 'PDO extension is not loaded' => [PDO::class, false];
            return;
        }

        foreach (array_merge([PDO::class], PDOAutoInstrumentation::findInternalPdoSubclasses()) as $className) {
            yield $className . ': new' => [$className, false];
            if (self::isPdoConnectSupported()) {
                yield $className . ': connect' => [$className, true];
            }
        }
    }

    /**
     * @dataProvider dataProviderForTestDbInfoIsUsedForSpans
     */
    public function testDbInfoIsUsedForSpans(string $className, bool $useConnect): void
    {
        $pdo = self::newPdoWithoutConnecting($className);
        if ($useConnect) {
            // PDO::connect() is a static method that does not call PDO::__construct()
            $this->callIntercepted($className, 'connect', /* thisObj */ null, [self::DSN], /* retVal */ $pdo);
        } else {
            $this->callIntercepted($className, '__construct', $pdo, [self::DSN], /* retVal */ null);
        }

        $stmt = (new ReflectionClass(PDOStatement::class))->newInstanceWithoutConstructor();
        $tx = ElasticApm::beginCurrentTransaction('test_TX_name', 'test_TX_type');
        $this->callIntercepted($className, 'query', $pdo, [self::SQL], /* retVal */ false);
        $this->callIntercepted($className, 'prepare', $pdo, [self::SQL], /* retVal */ $stmt);
        $this->callIntercepted(PDOStatement::class, 'execute', $stmt, [], /* retVal */ true);
        $tx->end();

        $expectedSuffix = ' / ' . self::EXPECTED_DB_TYPE . ' / ' . self::EXPECTED_DB_TYPE . '/' . self::EXPECTED_DB_NAME;
        self::assertSame(['PDOStatement::execute' . $expectedSuffix, self::SQL . $expectedSuffix], $this->spansDesc());
        $this->assertNothingLoggedAtWarningOrWorse();
    }
}
