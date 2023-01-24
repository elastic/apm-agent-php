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

/** @noinspection RequiredAttributes */

declare(strict_types=1);

namespace ElasticApmTests\ComponentTests;

use Elastic\Apm\Impl\AutoInstrument\PDOAutoInstrumentation;
use Elastic\Apm\Impl\Config\OptionNames;
use Elastic\Apm\Impl\Log\LoggableToString;
use Elastic\Apm\Impl\Util\ClassNameUtil;
use ElasticApmTests\ComponentTests\Util\AppCodeHostParams;
use ElasticApmTests\ComponentTests\Util\AppCodeRequestParams;
use ElasticApmTests\ComponentTests\Util\AppCodeTarget;
use ElasticApmTests\ComponentTests\Util\AutoInstrumentationUtilForTests;
use ElasticApmTests\ComponentTests\Util\ComponentTestCaseBase;
use ElasticApmTests\ComponentTests\Util\DbAutoInstrumentationUtilForTests;
use ElasticApmTests\ComponentTests\Util\ExpectedEventCounts;
use ElasticApmTests\Util\DataProviderForTestBuilder;
use ElasticApmTests\Util\DbSpanExpectationsBuilder;
use ElasticApmTests\Util\SpanExpectations;
use ElasticApmTests\Util\SpanSequenceValidator;
use PDO;

/**
 * @group smoke
 * @group does_not_require_external_services
 */
final class PDOTest extends ComponentTestCaseBase
{
    private const CONNECTION_STRING_PREFIX = 'sqlite:';

    public const TEMP_DB_NAME = '<temporary database>';
    public const MEMORY_DB_NAME = 'memory';
    public const FILE_DB_NAME = '<file DB>';

    private const MESSAGES
        = [
            'Just testing...'    => 1,
            'More testing...'    => 22,
            'SQLite3 is cool...' => 333,
        ];

    private const CREATE_TABLE_SQL
        = /** @lang text */
        'CREATE TABLE messages (
            id INTEGER PRIMARY KEY,
            text TEXT,
            time INTEGER)';

    private const INSERT_SQL
        = /** @lang text */
        'INSERT INTO messages (text, time) VALUES (:text, :time)';

    private const SELECT_SQL
        = /** @lang text */
        'SELECT * FROM messages';

    private static function buildConnectionString(string $dbName): string
    {
        // https://www.php.net/manual/en/ref.pdo-sqlite.connection.php

        switch ($dbName) {
            case self::TEMP_DB_NAME:
                return self::CONNECTION_STRING_PREFIX;
            case self::MEMORY_DB_NAME:
                return self::CONNECTION_STRING_PREFIX . ':' . self::MEMORY_DB_NAME . ':';
            default:
                return self::CONNECTION_STRING_PREFIX . $dbName;
        }
    }

    /**
     * @return iterable<array{string}>
     */
    public function dataProviderForTestBuildConnectionString(): iterable
    {
        // https://www.php.net/manual/en/ref.pdo-sqlite.connection.php

        return self::adaptToSmoke(
            [
                // To create a database in memory, :memory: has to be appended to the DSN prefix
                yield [self::MEMORY_DB_NAME, 'sqlite::memory:'],

                // To create a database in memory, :memory: has to be appended to the DSN prefix
                yield ['/opt/databases/my_db.sqlite', 'sqlite:/opt/databases/my_db.sqlite'],

                // If the DSN consists of the DSN prefix only, a temporary database is used,
                // which is deleted when the connection is closed
                yield [self::TEMP_DB_NAME, 'sqlite:'],
            ]
        );
    }

    /**
     * @dataProvider dataProviderForTestBuildConnectionString
     *
     * @param string $dbName
     * @param string $expectedDbConnectionString
     */
    public function testBuildConnectionString(string $dbName, string $expectedDbConnectionString): void
    {
        $dbgCtx = LoggableToString::convert(['$dbLocation' => $dbName,]);
        $actualDbConnectionString = self::buildConnectionString($dbName);
        self::assertSame($expectedDbConnectionString, $actualDbConnectionString, $dbgCtx);
    }

    public function testPrerequisitesSatisfied(): void
    {
        $extensionName = 'pdo_sqlite';
        self::assertTrue(extension_loaded($extensionName), 'Required extension ' . $extensionName . ' is not loaded');
    }

    public function testIsAutoInstrumentationEnabled(): void
    {
        $this->implTestIsAutoInstrumentationEnabled(
            PDOAutoInstrumentation::class /* <- instrClass */,
            ['pdo', 'db'] /* <- expectedNames */
        );
    }

    /**
     * @return iterable<array{array<string, mixed>}>
     */
    public function dataProviderForTestAutoInstrumentation(): iterable
    {
        $disableInstrumentationsVariants = [
            ''    => true,
            'pdo' => false,
            'db'  => false,
        ];

        $dbNames = [];
        $dbNames[] = self::MEMORY_DB_NAME;
        $dbNames[] = self::TEMP_DB_NAME;
        $dbNames[] = self::FILE_DB_NAME;

        /** @var iterable<array{array<string, mixed>}> $result */
        $result = (new DataProviderForTestBuilder())
            ->addGeneratorOnlyFirstValueCombinable(
                AutoInstrumentationUtilForTests::disableInstrumentationsDataProviderGenerator(
                    $disableInstrumentationsVariants
                )
            )
            ->addKeyedDimensionOnlyFirstValueCombinable(DbAutoInstrumentationUtilForTests::DB_NAME_KEY, $dbNames)
            ->addGeneratorOnlyFirstValueCombinable(
                DbAutoInstrumentationUtilForTests::wrapTxRelatedArgsDataProviderGenerator()
            )
            ->wrapResultIntoArray()
            ->build();

        return self::adaptToSmoke($result);
    }

    /**
     * @param array<string, mixed> $args
     * @param ?string             &$dbName
     * @param ?bool               &$wrapInTx
     * @param ?bool               &$rollback
     */
    public static function extractSharedArgs(
        array $args,
        /* out */ ?string &$dbName,
        /* out */ ?bool &$wrapInTx,
        /* out */ ?bool &$rollback
    ): void {
        $dbName = self::getMandatoryAppCodeArg($args, DbAutoInstrumentationUtilForTests::DB_NAME_KEY);
        self::assertIsString($dbName);
        $wrapInTx = self::getMandatoryAppCodeArg($args, DbAutoInstrumentationUtilForTests::WRAP_IN_TX_KEY);
        self::assertIsBool($wrapInTx);
        $rollback = self::getMandatoryAppCodeArg($args, DbAutoInstrumentationUtilForTests::ROLLBACK_KEY);
        self::assertIsBool($rollback);
    }

    /**
     * @param array<string, mixed> $appCodeArgs
     */
    public static function appCodeForTestAutoInstrumentation(array $appCodeArgs): void
    {
        self::extractSharedArgs(
            $appCodeArgs,
            /* out */ $dbName,
            /* out */ $wrapInTx,
            /* out */ $rollback
        );

        $pdo = new PDO(self::buildConnectionString($dbName));
        self::assertTrue($pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION));
        if ($wrapInTx) {
            self::assertTrue($pdo->beginTransaction());
        }

        self::assertNotFalse($pdo->exec(self::CREATE_TABLE_SQL));

        self::assertNotFalse($stmt = $pdo->prepare(self::INSERT_SQL));
        $boundMsgText = '';
        $boundMsgTime = 0;
        self::assertTrue($stmt->bindParam(':text', /* ref */ $boundMsgText));
        self::assertTrue($stmt->bindParam(':time', /* ref */ $boundMsgTime));
        foreach (self::MESSAGES as $msgText => $msgTime) {
            $boundMsgText = $msgText;
            $boundMsgTime = $msgTime;
            self::assertTrue($stmt->execute());
        }

        self::assertNotFalse($queryResult = $pdo->query(self::SELECT_SQL));
        foreach ($queryResult as $row) {
            $dbgCtx = LoggableToString::convert(['$row' => $row, '$queryResult' => $queryResult]);
            $msgText = $row['text'];
            self::assertIsString($msgText);
            self::assertArrayHasKey($msgText, self::MESSAGES, $dbgCtx);
            self::assertEquals(self::MESSAGES[$msgText], $row['time'], $dbgCtx);
        }

        if ($wrapInTx) {
            self::assertTrue($rollback ? $pdo->rollback() : $pdo->commit());
        }
    }

    /**
     * @dataProvider dataProviderForTestAutoInstrumentation
     *
     * @param array<string, mixed> $testArgs
     */
    public function testAutoInstrumentation(array $testArgs): void
    {
        self::runAndEscalateLogLevelOnFailure(
            self::buildDbgDescForTestWithArtgs(__CLASS__, __FUNCTION__, $testArgs),
            function () use ($testArgs): void {
                $this->implTestAutoInstrumentation($testArgs);
            }
        );
    }

    /**
     * @param array<string, mixed> $testArgs
     */
    private function implTestAutoInstrumentation(array $testArgs): void
    {
        $disableInstrumentationsOptVal = self::getMandatoryAppCodeArg(
            $testArgs,
            AutoInstrumentationUtilForTests::DISABLE_INSTRUMENTATIONS_KEY
        );
        self::assertIsString($disableInstrumentationsOptVal);
        $isInstrumentationEnabled = self::getMandatoryAppCodeArg(
            $testArgs,
            AutoInstrumentationUtilForTests::IS_INSTRUMENTATION_ENABLED_KEY
        );

        self::extractSharedArgs(
            $testArgs,
            /* out */ $dbNameArg,
            /* out */ $wrapInTx,
            /* out */ $rollback
        );

        $testCaseHandle = $this->getTestCaseHandle();

        $appCodeArgs = $testArgs;

        $dbName = $dbNameArg;
        if ($dbNameArg === self::FILE_DB_NAME) {
            $resourcesClient = $testCaseHandle->getResourcesClient();
            $dbFileFullPath = $resourcesClient->createTempFile(ClassNameUtil::fqToShort(__CLASS__) . '_temp_DB');
            $dbName = $dbFileFullPath;
            $appCodeArgs[DbAutoInstrumentationUtilForTests::DB_NAME_KEY] = $dbName;
        }

        $sharedExpectations = DbSpanExpectationsBuilder::default(/* dbType: */ 'sqlite', $dbName);
        $expectationsBuilder = new DbSpanExpectationsBuilder($sharedExpectations);
        /** @var SpanExpectations[] $expectedSpans */
        $expectedSpans = [];
        if ($isInstrumentationEnabled) {
            if ($wrapInTx) {
                $expectedSpans[] = $expectationsBuilder->fromClassMethodNames('PDO', 'beginTransaction');
            }

            $expectedSpans[] = $expectationsBuilder->fromStatement(self::CREATE_TABLE_SQL);
            foreach (self::MESSAGES as $ignored) {
                $expectedSpans[] = $expectationsBuilder->fromStatement(self::INSERT_SQL);
            }
            $expectedSpans[] = $expectationsBuilder->fromStatement(self::SELECT_SQL);

            if ($wrapInTx) {
                $expectedSpans[] = $expectationsBuilder->fromClassMethodNames('PDO', $rollback ? 'rollBack' : 'commit');
            }
        }

        $appCodeHost = $testCaseHandle->ensureMainAppCodeHost(
            function (AppCodeHostParams $appCodeParams) use ($disableInstrumentationsOptVal): void {
                if (!empty($disableInstrumentationsOptVal)) {
                    $appCodeParams->setAgentOption(
                        OptionNames::DISABLE_INSTRUMENTATIONS,
                        $disableInstrumentationsOptVal
                    );
                }
            }
        );
        $appCodeHost->sendRequest(
            AppCodeTarget::asRouted([__CLASS__, 'appCodeForTestAutoInstrumentation']),
            function (AppCodeRequestParams $appCodeRequestParams) use ($appCodeArgs): void {
                $appCodeRequestParams->setAppCodeArgs($appCodeArgs);
            }
        );

        $dataFromAgent = $testCaseHandle->waitForDataFromAgent(
            (new ExpectedEventCounts())->transactions(1)->spans(count($expectedSpans))
        );

        SpanSequenceValidator::updateExpectationsEndTime($expectedSpans);
        SpanSequenceValidator::assertSequenceAsExpected($expectedSpans, array_values($dataFromAgent->idToSpan));
    }
}
