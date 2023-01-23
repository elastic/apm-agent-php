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

declare(strict_types=1);

namespace ElasticApmTests\ComponentTests\Util;

use Elastic\Apm\Impl\AutoInstrument\AutoInstrumentationBase;
use Elastic\Apm\Impl\Config\OptionNames;
use Elastic\Apm\Impl\GlobalTracerHolder;
use Elastic\Apm\Impl\Log\Level as LogLevel;
use Elastic\Apm\Impl\Log\LoggableToString;
use Elastic\Apm\Impl\Tracer;
use Elastic\Apm\Impl\Util\ClassNameUtil;
use Elastic\Apm\Impl\Util\ExceptionUtil;
use Elastic\Apm\Impl\Util\RangeUtil;
use ElasticApmTests\Util\DataFromAgent;
use ElasticApmTests\Util\TestCaseBase;
use ElasticApmTests\Util\TransactionDto;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ComponentTestCaseBase extends TestCaseBase
{
    /** @var ?TestCaseHandle */
    private $testCaseHandle = null;

    public const LOG_LEVEL_FOR_PROD_CODE_KEY = 'PROD_CODE';
    public const LOG_LEVEL_FOR_TEST_CODE_KEY = 'TEST_CODE';
    private const LOG_LEVEL_FOR_CODE_KEYS = [self::LOG_LEVEL_FOR_PROD_CODE_KEY, self::LOG_LEVEL_FOR_TEST_CODE_KEY];

    protected function initTestCaseHandle(?int $escalatedLogLevelForProdCode = null): TestCaseHandle
    {
        if ($this->testCaseHandle !== null) {
            return $this->testCaseHandle;
        }
        ComponentTestsPhpUnitExtension::initSingletons();
        $this->testCaseHandle = new TestCaseHandle($escalatedLogLevelForProdCode);
        return $this->testCaseHandle;
    }

    protected function getTestCaseHandle(): TestCaseHandle
    {
        return $this->initTestCaseHandle();
    }

    /** @inheritDoc */
    public function tearDown(): void
    {
        if ($this->testCaseHandle !== null) {
            $this->testCaseHandle->tearDown();
            $this->testCaseHandle = null;
        }
    }

    public static function appCodeEmpty(): void
    {
    }

    public static function getTracerFromAppCode(): Tracer
    {
        $tracer = GlobalTracerHolder::getValue();
        TestCase::assertInstanceOf(Tracer::class, $tracer);
        /** @var Tracer $tracer */
        return $tracer;
    }

    protected static function buildResourcesClientForAppCode(): ResourcesClient
    {
        $resCleanerId = AmbientContextForTests::testConfig()->dataPerProcess->resourcesCleanerSpawnedProcessInternalId;
        TestCase::assertNotNull($resCleanerId);
        $resCleanerPort = AmbientContextForTests::testConfig()->dataPerProcess->resourcesCleanerPort;
        TestCase::assertNotNull($resCleanerPort);
        return new ResourcesClient($resCleanerId, $resCleanerPort);
    }

    /**
     * @param string                     $optName
     * @param null|string|int|float|bool $optVal
     *
     * @return DataFromAgent
     */
    protected function configTestImpl(string $optName, $optVal): DataFromAgent
    {
        $testCaseHandle = $this->getTestCaseHandle();
        $appCodeHost = $testCaseHandle->ensureMainAppCodeHost(
            function (AppCodeHostParams $appCodeParams) use ($optName, $optVal): void {
                if ($optVal !== null) {
                    $appCodeParams->setAgentOption($optName, $optVal);
                }
            }
        );
        $appCodeHost->sendRequest(AppCodeTarget::asRouted([__CLASS__, 'appCodeEmpty']));
        return $this->waitForOneEmptyTransaction($testCaseHandle);
    }

    /**
     * @param array<string, mixed> $appCodeArgs
     * @param string               $appArgNameKey
     *
     * @return mixed
     */
    protected static function getMandatoryAppCodeArg(array $appCodeArgs, string $appArgNameKey)
    {
        if (!array_key_exists($appArgNameKey, $appCodeArgs)) {
            throw new RuntimeException(
                ExceptionUtil::buildMessage(
                    'Expected key is not found in app code args',
                    ['appArgNameKey' => $appArgNameKey, 'appCodeArgs' => $appCodeArgs]
                )
            );
        }
        return $appCodeArgs[$appArgNameKey];
    }

    /**
     * @param array<string, mixed> $argsMap
     *
     * @return bool
     */
    protected static function getBoolFromArgsMap(string $argKey, array $argsMap): bool
    {
        self::assertArrayHasKey($argKey, $argsMap);
        $val = $argsMap[$argKey];
        self::assertIsBool($val, LoggableToString::convert(['argsMap' => $argsMap]));
        return $val;
    }

    public static function isSmoke(): bool
    {
        ComponentTestsPhpUnitExtension::initSingletons();
        return AmbientContextForTests::testConfig()->isSmoke();
    }

    public static function isMainAppCodeHostHttp(): bool
    {
        ComponentTestsPhpUnitExtension::initSingletons();
        return AmbientContextForTests::testConfig()->appCodeHostKind()->isHttp();
    }

    protected function skipIfMainAppCodeHostIsNotCliScript(): bool
    {
        if (self::isMainAppCodeHostHttp()) {
            self::dummyAssert();
            return true;
        }

        return false;
    }

    protected function skipIfMainAppCodeHostIsNotHttp(): bool
    {
        if (!self::isMainAppCodeHostHttp()) {
            self::dummyAssert();
            return true;
        }

        return false;
    }

    protected function waitForOneEmptyTransaction(TestCaseHandle $testCaseHandle): DataFromAgentPlusRaw
    {
        $dataFromAgent = $testCaseHandle->waitForDataFromAgent((new ExpectedEventCounts())->transactions(1));
        $this->verifyOneTransactionNoSpans($dataFromAgent);
        return $dataFromAgent;
    }

    protected function verifyOneTransactionNoSpans(DataFromAgent $dataFromAgent): TransactionDto
    {
        $this->assertEmpty($dataFromAgent->idToSpan);

        $tx = $dataFromAgent->singleTransaction();
        $this->assertSame(0, $tx->startedSpansCount);
        $this->assertSame(0, $tx->droppedSpansCount);
        $this->assertNull($tx->parentId);
        return $tx;
    }

    /**
     * @param class-string<AutoInstrumentationBase> $instrClassName
     * @param string[]                              $expectedNames
     *
     * @return void
     */
    protected static function implTestIsAutoInstrumentationEnabled(string $instrClassName, array $expectedNames): void
    {
        /** @var AutoInstrumentationBase $instr */
        $instr = new $instrClassName(self::buildTracerForTests()->build());
        $actualNames = $instr->otherNames();
        $actualNames[] = $instr->name();
        self::assertEqualAsSets($expectedNames, $actualNames);
        self::assertTrue($instr->isEnabled());

        /**
         * @param string $name
         *
         * @return iterable<string>
         */
        $genDisabledVariants = function (string $name): iterable {
            yield $name;
            yield '*' . $name;
            yield $name . '*';
            yield '*' . $name . '*';
            yield '*someOtherDummyInstrumentationA*, ' . $name;
            yield $name . ', *someOtherDummyInstrumentationB*';
            yield '*someOtherDummyInstrumentationA*, ' . $name . ', *someOtherDummyInstrumentationB*';
        };

        foreach ($expectedNames as $name) {
            foreach ($genDisabledVariants($name) as $disableInstrumentationsOptVal) {
                $tracer = self::buildTracerForTests()
                              ->withConfig(OptionNames::DISABLE_INSTRUMENTATIONS, $disableInstrumentationsOptVal)
                              ->build();
                $instr = new $instrClassName($tracer);
                self::assertFalse($instr->isEnabled(), $disableInstrumentationsOptVal);
            }
        }

        /**
         * @return iterable<string>
         */
        $genEnabledVariants = function (): iterable {
            yield '*someOtherDummyInstrumentation*';
            yield '*someOtherDummyInstrumentationA*,  *someOtherDummyInstrumentationB*';
        };

        foreach ($genEnabledVariants() as $disableInstrumentationsOptVal) {
            $tracer = self::buildTracerForTests()
                          ->withConfig(OptionNames::DISABLE_INSTRUMENTATIONS, $disableInstrumentationsOptVal)
                          ->build();
            $instr = new $instrClassName($tracer);
            self::assertTrue($instr->isEnabled(), $disableInstrumentationsOptVal);
        }
    }

    /**
     * @template T
     *
     * @param iterable<T> $variants
     *
     * @return iterable<T>
     */
    public function adaptToSmoke(iterable $variants): iterable
    {
        if (!self::isSmoke()) {
            return $variants;
        }
        foreach ($variants as $key => $value) {
            return [$key => $value];
        }
        return [];
    }

    /**
     * @return iterable<array{bool}>
     */
    public function boolDataProviderAdaptedToSmoke(): iterable
    {
        return self::adaptToSmoke(self::boolDataProvider());
    }

    /**
     * @param string           $dbgTestDesc
     * @param callable(): void $testCall
     *
     * @return void
     */
    protected function runAndEscalateLogLevelOnFailure(string $dbgTestDesc, callable $testCall): void
    {
        /** @var AssertionFailedError $initiallyFailedTestException */
        $initiallyFailedTestException = null;
        try {
            $testCall();
            return;
        } catch (AssertionFailedError $ex) {
            $initiallyFailedTestException = $ex;
        }

        $logger = $this->getLogger(__NAMESPACE__, __CLASS__, __FILE__)->addContext('dbgTestDesc', $dbgTestDesc);
        if ($this->testCaseHandle === null) {
            ($loggerProxy = $logger->ifWarningLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Test failed but $this->testCaseHandle is null - NOT re-running the test with escalated log levels'
            );
            throw $initiallyFailedTestException;
        }
        $initiallyFailedTestLogLevels = $this->getCurrentLogLevels($this->testCaseHandle);
        $logger->addAllContext(
            [
                'initiallyFailedTestLogLevels' => $initiallyFailedTestLogLevels,
                'initiallyFailedTestException' => $initiallyFailedTestException,
            ]
        );

        $rerunCount = 0;
        foreach (self::generateEscalatedLogLevels($initiallyFailedTestLogLevels) as $escalatedLogLevels) {
            $this->tearDown();

            ++$rerunCount;
            $loggerPerIteration = $logger->inherit()->addAllContext(
                ['rerunCount' => $rerunCount, 'escalatedLogLevels' => $escalatedLogLevels]
            );

            $logLevelOptName = AllComponentTestsOptionsMetadata::LOG_LEVEL_OPTION_NAME;
            $logLevelEnvVarName = ConfigUtilForTests::envVarNameForTestOption($logLevelOptName);
            $escalatedLogLevelForTestCode = $escalatedLogLevels[self::LOG_LEVEL_FOR_TEST_CODE_KEY];
            putenv($logLevelEnvVarName . '=' . LogLevel::intToName($escalatedLogLevelForTestCode));
            AmbientContextForTests::reconfigure();
            Assert::assertSame($escalatedLogLevelForTestCode, AmbientContextForTests::testConfig()->logLevel);

            $this->initTestCaseHandle($escalatedLogLevels[self::LOG_LEVEL_FOR_PROD_CODE_KEY]);

            ($loggerProxy = $loggerPerIteration->ifInfoLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log('Test failed - re-running it with escalated log levels...');
            try {
                $testCall();
                ($loggerProxy = $loggerPerIteration->ifWarningLevelEnabled(__LINE__, __FUNCTION__))
                && $loggerProxy->log('Test re-run with escalated log levels did NOT fail (which is bad :(');
            } catch (AssertionFailedError $ex) {
                ($loggerProxy = $loggerPerIteration->ifInfoLevelEnabled(__LINE__, __FUNCTION__))
                && $loggerProxy->log('Test re-run with escalated log levels failed (which is good :)', ['ex' => $ex]);
                throw $ex;
            }
        }

        ($loggerProxy = $logger->ifCriticalLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'All test re-runs with escalated log levels did NOT fail (which is bad :('
            . ' - re-throwing original test failure exception'
        );
        throw $initiallyFailedTestException;
    }

    /**
     * @param class-string $testClass
     * @param string       $testFunc
     *
     * @return string
     */
    protected static function buildDbgDescForTest(string $testClass, string $testFunc): string
    {
        return ClassNameUtil::fqToShort($testClass) . '::' . $testFunc;
    }

    /**
     * @param class-string         $testClass
     * @param string               $testFunc
     * @param array<string, mixed> $testArgs
     *
     * @return string
     */
    protected static function buildDbgDescForTestWithArtgs(string $testClass, string $testFunc, array $testArgs): string
    {
        return ClassNameUtil::fqToShort($testClass) . '::' . $testFunc
               . '(' . LoggableToString::convert($testArgs) . ')';
    }

    /**
     * @param TestCaseHandle $testCaseHandle
     *
     * @return array<string, int>
     */
    private function getCurrentLogLevels(TestCaseHandle $testCaseHandle): array
    {
        $result = [];
        $prodCodeLogLevels = $testCaseHandle->getProdCodeLogLevels();
        Assert::assertNotEmpty($prodCodeLogLevels);
        $result[self::LOG_LEVEL_FOR_PROD_CODE_KEY] = min($prodCodeLogLevels);
        $result[self::LOG_LEVEL_FOR_TEST_CODE_KEY] = AmbientContextForTests::testConfig()->logLevel;
        /** @var array<string, int> $result */
        return $result;
    }

    /**
     * @param array<string, int> $initialLevels
     *
     * @return iterable<array<string, int>>
     */
    public static function generateEscalatedLogLevels(array $initialLevels): iterable
    {
        Assert::assertNotEmpty($initialLevels);

        /**
         * @param array<string, int> $currentLevels
         *
         * @return bool
         */
        $haveCurrentLevelsReachedInitial = function (array $currentLevels) use ($initialLevels): bool {
            foreach ($initialLevels as $levelTypeKey => $initialLevel) {
                if ($initialLevel < $currentLevels[$levelTypeKey]) {
                    return false;
                }
            }
            return true;
        };

        /** @var int $minInitialLevel */
        $minInitialLevel = min($initialLevels);
        foreach (RangeUtil::generateDown(LogLevel::getHighest(), $minInitialLevel) as $baseLevel) {
            Assert::assertGreaterThan(LogLevel::OFF, $baseLevel);
            $currentLevels = [];
            foreach (self::LOG_LEVEL_FOR_CODE_KEYS as $levelTypeKey) {
                $currentLevels[$levelTypeKey] = $baseLevel;
            }
            yield $currentLevels;
            foreach (self::LOG_LEVEL_FOR_CODE_KEYS as $levelTypeKey) {
                $currentLevels[$levelTypeKey] = $baseLevel - 1;
                if (!$haveCurrentLevelsReachedInitial($currentLevels)) {
                    yield $currentLevels;
                }
                $currentLevels[$levelTypeKey] = $baseLevel;
            }
        }
    }
}
