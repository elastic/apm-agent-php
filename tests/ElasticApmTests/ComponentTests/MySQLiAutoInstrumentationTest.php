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

use Elastic\Apm\Impl\AutoInstrument\MySQLiAutoInstrumentation;
use Elastic\Apm\Impl\Config\OptionNames;
use Elastic\Apm\Impl\Log\LoggableToString;
use Elastic\Apm\Impl\Util\TextUtil;
use ElasticApmTests\ComponentTests\MySQLi\ApiFacade;
use ElasticApmTests\ComponentTests\MySQLi\MySQLiDbSpanDataExpectationsBuilder;
use ElasticApmTests\ComponentTests\MySQLi\MySQLiResultWrapped;
use ElasticApmTests\ComponentTests\MySQLi\MySQLiWrapped;
use ElasticApmTests\ComponentTests\Util\AmbientContextForTests;
use ElasticApmTests\ComponentTests\Util\AppCodeHostParams;
use ElasticApmTests\ComponentTests\Util\AppCodeRequestParams;
use ElasticApmTests\ComponentTests\Util\AppCodeTarget;
use ElasticApmTests\ComponentTests\Util\AutoInstrumentationUtilForTests;
use ElasticApmTests\ComponentTests\Util\ComponentTestCaseBase;
use ElasticApmTests\ComponentTests\Util\DbAutoInstrumentationUtilForTests;
use ElasticApmTests\ComponentTests\Util\ExpectedEventCounts;
use ElasticApmTests\Util\DataProviderForTestBuilder;
use ElasticApmTests\Util\MixedMap;
use ElasticApmTests\Util\SpanExpectations;
use ElasticApmTests\Util\SpanSequenceValidator;
use PHPUnit\Framework\TestCase;

/**
 * @group smoke
 * @group requires_external_services
 * @group requires_mysql_external_service
 */
final class MySQLiAutoInstrumentationTest extends ComponentTestCaseBase
{
    private const IS_OOP_API_KEY = 'IS_OOP_API';

    public const CONNECT_DB_NAME_KEY = 'CONNECT_DB_NAME';
    public const WORK_DB_NAME_KEY = 'WORK_DB_NAME';

    private const QUERY_KIND_KEY = 'QUERY_KIND';
    private const QUERY_KIND_QUERY = 'query';
    private const QUERY_KIND_REAL_QUERY = 'real_query';
    private const QUERY_KIND_MULTI_QUERY = 'multi_query';
    private const QUERY_KIND_ALL_VALUES = [self::QUERY_KIND_QUERY, self::QUERY_KIND_REAL_QUERY, self::QUERY_KIND_MULTI_QUERY];

    private const DB_TYPE = 'mysql';

    private const MESSAGES
        = [
            'Just testing...'    => 1,
            'More testing...'    => 22,
            'SQLite3 is cool...' => 333,
        ];

    private const DROP_DATABASE_IF_EXISTS_SQL_PREFIX
        = /** @lang text */
        'DROP DATABASE IF EXISTS ';

    private const CREATE_DATABASE_SQL_PREFIX
        = /** @lang text */
        'CREATE DATABASE ';

    private const CREATE_DATABASE_IF_NOT_EXISTS_SQL_PREFIX
        = /** @lang text */
        'CREATE DATABASE IF NOT EXISTS ';

    private const CREATE_TABLE_SQL
        = /** @lang text */
        'CREATE TABLE messages (
            id INT AUTO_INCREMENT,
            text TEXT,
            time INTEGER,
            PRIMARY KEY(id)
        )';

    private const INSERT_SQL
        = /** @lang text */
        'INSERT INTO messages (text, time) VALUES (?, ?)';

    private const SELECT_SQL
        = /** @lang text */
        'SELECT * FROM messages';

    /**
     * Tests in this class specifiy expected spans individually
     * so Span Compression feature should be disabled.
     *
     * @inheritDoc
     */
    protected function isSpanCompressionCompatible(): bool
    {
        return false;
    }

    public function testPrerequisitesSatisfied(): void
    {
        $extensionName = 'mysqli';
        self::assertTrue(extension_loaded($extensionName), 'Required extension ' . $extensionName . ' is not loaded');

        self::assertNotNull(AmbientContextForTests::testConfig()->mysqlHost);
        self::assertNotNull(AmbientContextForTests::testConfig()->mysqlPort);
        self::assertNotNull(AmbientContextForTests::testConfig()->mysqlUser);
        self::assertNotNull(AmbientContextForTests::testConfig()->mysqlPassword);
        self::assertNotNull(AmbientContextForTests::testConfig()->mysqlDb);

        $mySQLiApiFacade = new ApiFacade(/* isOOPApi */ true);
        $mySQLi = $mySQLiApiFacade->connect(
            AmbientContextForTests::testConfig()->mysqlHost,
            AmbientContextForTests::testConfig()->mysqlPort,
            AmbientContextForTests::testConfig()->mysqlUser,
            AmbientContextForTests::testConfig()->mysqlPassword,
            AmbientContextForTests::testConfig()->mysqlDb
        );
        self::assertNotNull($mySQLi);
        self::assertTrue($mySQLi->ping());
    }

    public function testIsAutoInstrumentationEnabled(): void
    {
        $this->implTestIsAutoInstrumentationEnabled(
            MySQLiAutoInstrumentation::class /* <- instrClass */,
            ['mysqli', 'db'] /* <- expectedNames */
        );
    }

    /**
     * @param MySQLiWrapped $mySQLi
     * @param string[]      $queries
     * @param string        $kind
     *
     * @return void
     */
    private static function runQueriesUsingKind(MySQLiWrapped $mySQLi, array $queries, string $kind): void
    {
        switch ($kind) {
            case self::QUERY_KIND_MULTI_QUERY:
                $multiQuery = '';
                foreach ($queries as $query) {
                    if (!TextUtil::isEmptyString($multiQuery)) {
                        $multiQuery .= ';';
                    }
                    $multiQuery .= $query;
                }
                TestCase::assertTrue($mySQLi->multiQuery($multiQuery));
                while (true) {
                    $result = $mySQLi->storeResult();
                    if ($result === false) {
                        TestCase::assertEmpty($mySQLi->error());
                    } else {
                        $result->close();
                    }
                    if (!$mySQLi->moreResults()) {
                        break;
                    }
                    TestCase::assertTrue($mySQLi->nextResult());
                }
                break;
            case self::QUERY_KIND_REAL_QUERY:
                foreach ($queries as $query) {
                    TestCase::assertTrue($mySQLi->realQuery($query));
                }
                break;
            case self::QUERY_KIND_QUERY:
                foreach ($queries as $query) {
                    TestCase::assertTrue($mySQLi->query($query));
                }
                break;
            default:
                TestCase::fail();
        }
    }

    /**
     * @param MySQLiDbSpanDataExpectationsBuilder $expectationsBuilder
     * @param string[]                            $queries
     * @param string                              $kind
     * @param SpanExpectations[]                 &$expectedSpans
     */
    private static function addExpectationsForQueriesUsingKind(
        MySQLiDbSpanDataExpectationsBuilder $expectationsBuilder,
        array $queries,
        string $kind,
        array &$expectedSpans
    ): void {
        switch ($kind) {
            case self::QUERY_KIND_MULTI_QUERY:
                $multiQuery = '';
                foreach ($queries as $query) {
                    if (!TextUtil::isEmptyString($multiQuery)) {
                        $multiQuery .= ';';
                    }
                    $multiQuery .= $query;
                }
                $expectedSpans[] = $expectationsBuilder->fromStatement($multiQuery);
                break;
            case self::QUERY_KIND_QUERY:
            case self::QUERY_KIND_REAL_QUERY:
                foreach ($queries as $query) {
                    $expectedSpans[] = $expectationsBuilder->fromStatement($query);
                }
                break;
            default:
                TestCase::fail();
        }
    }

    /**
     * @return string[]
     */
    private static function allDbNames(): array
    {
        $defaultDbName = AmbientContextForTests::testConfig()->mysqlDb;
        TestCase::assertNotNull($defaultDbName);
        return [$defaultDbName, $defaultDbName . '_ALT'];
    }

    /**
     * @return string[]
     */
    private static function queriesToResetDbState(): array
    {
        $queries = [];
        foreach (self::allDbNames() as $dbName) {
            $queries[] = self::DROP_DATABASE_IF_EXISTS_SQL_PREFIX . $dbName;
        }
        $queries[] = self::CREATE_DATABASE_SQL_PREFIX . AmbientContextForTests::testConfig()->mysqlDb;
        return $queries;
    }

    private static function resetDbState(MySQLiWrapped $mySQLi, string $queryKind): void
    {
        $queries = self::queriesToResetDbState();
        self::runQueriesUsingKind($mySQLi, $queries, $queryKind);
    }

    /**
     * @param MySQLiDbSpanDataExpectationsBuilder $expectationsBuilder
     * @param string                              $queryKind
     * @param SpanExpectations[]             &$expectedSpans
     */
    private static function addExpectationsForResetDbState(
        MySQLiDbSpanDataExpectationsBuilder $expectationsBuilder,
        string $queryKind,
        /* out */ array &$expectedSpans
    ): void {
        $queries = self::queriesToResetDbState();
        self::addExpectationsForQueriesUsingKind($expectationsBuilder, $queries, $queryKind, /* out */ $expectedSpans);
    }

    /**
     * @return iterable<array{MixedMap}>
     */
    public function dataProviderForTestAutoInstrumentation(): iterable
    {
        $disableInstrumentationsVariants = [
            ''       => true,
            'mysqli' => false,
            'db'     => false,
        ];

        /** @var array<?string> $connectDbNameVariants */
        $connectDbNameVariants = [AmbientContextForTests::testConfig()->mysqlDb];
        if (ApiFacade::canDbNameBeNull()) {
            $connectDbNameVariants[] = null;
        }

        $result = (new DataProviderForTestBuilder())
            ->addGeneratorOnlyFirstValueCombinable(AutoInstrumentationUtilForTests::disableInstrumentationsDataProviderGenerator($disableInstrumentationsVariants))
            ->addBoolKeyedDimensionAllValuesCombinable(self::IS_OOP_API_KEY)
            ->addCartesianProductOnlyFirstValueCombinable([self::CONNECT_DB_NAME_KEY => $connectDbNameVariants, self::WORK_DB_NAME_KEY    => self::allDbNames()])
            ->addKeyedDimensionOnlyFirstValueCombinable(self::QUERY_KIND_KEY, self::QUERY_KIND_ALL_VALUES)
            ->addGeneratorOnlyFirstValueCombinable(DbAutoInstrumentationUtilForTests::wrapTxRelatedArgsDataProviderGenerator())
            ->build();

        return self::adaptToSmoke(DataProviderForTestBuilder::convertEachDataSetToMixedMap($result));
    }

    /**
     * @param MixedMap  $args
     * @param ?bool    &$isOOPApi
     * @param ?string  &$connectDbName
     * @param ?string  &$workDbName
     * @param ?string  &$queryKind
     * @param ?bool    &$wrapInTx
     * @param ?bool    &$rollback
     *
     * @param-out bool    $isOOPApi
     * @param-out ?string $connectDbName
     * @param-out string  $workDbName
     * @param-out string  $queryKind
     * @param-out bool    $wrapInTx
     * @param-out bool    $rollback
     */
    public static function extractSharedArgs(
        MixedMap $args,
        ?bool &$isOOPApi /* <- out */,
        ?string &$connectDbName /* <- out */,
        ?string &$workDbName /* <- out */,
        ?string &$queryKind /* <- out */,
        ?bool &$wrapInTx /* <- out */,
        ?bool &$rollback /* <- out */
    ): void {
        $isOOPApi = $args->getBool(self::IS_OOP_API_KEY);
        $connectDbName = $args->getNullableString(self::CONNECT_DB_NAME_KEY);
        $workDbName = $args->getString(self::WORK_DB_NAME_KEY);
        $queryKind = $args->getString(self::QUERY_KIND_KEY);
        $wrapInTx = $args->getBool(DbAutoInstrumentationUtilForTests::WRAP_IN_TX_KEY);
        $rollback = $args->getBool(DbAutoInstrumentationUtilForTests::ROLLBACK_KEY);
    }

    public static function appCodeForTestAutoInstrumentation(MixedMap $appCodeArgs): void
    {
        self::extractSharedArgs(
            $appCodeArgs,
            /* out */ $isOOPApi,
            /* out */ $connectDbName,
            /* out */ $workDbName,
            /* out */ $queryKind,
            /* out */ $wrapInTx,
            /* out */ $rollback
        );
        $host = $appCodeArgs->getString(DbAutoInstrumentationUtilForTests::HOST_KEY);
        $port = $appCodeArgs->getInt(DbAutoInstrumentationUtilForTests::PORT_KEY);
        $user = $appCodeArgs->getString(DbAutoInstrumentationUtilForTests::USER_KEY);
        $password = $appCodeArgs->getString(DbAutoInstrumentationUtilForTests::PASSWORD_KEY);

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        $mySQLiApiFacade = new ApiFacade($isOOPApi);
        $mySQLi = $mySQLiApiFacade->connect($host, $port, $user, $password, $connectDbName);
        self::assertNotNull($mySQLi);
        self::assertTrue($mySQLi->ping());

        if ($connectDbName !== $workDbName) {
            self::assertTrue($mySQLi->query(self::CREATE_DATABASE_IF_NOT_EXISTS_SQL_PREFIX . $workDbName));
            self::assertTrue($mySQLi->selectDb($workDbName));
        }

        self::assertTrue($mySQLi->query(self::CREATE_TABLE_SQL));

        if ($wrapInTx) {
            self::assertTrue($mySQLi->beginTransaction());
        }

        self::assertNotFalse($stmt = $mySQLi->prepare(self::INSERT_SQL));
        foreach (self::MESSAGES as $msgText => $msgTime) {
            self::assertTrue($stmt->bindParam('si', $msgText, $msgTime));
            self::assertTrue($stmt->execute());
        }
        self::assertTrue($stmt->close());

        self::assertInstanceOf(MySQLiResultWrapped::class, $queryResult = $mySQLi->query(self::SELECT_SQL));
        self::assertSame(count(self::MESSAGES), $queryResult->numRows());
        $rowCount = 0;
        while (true) {
            $row = $queryResult->fetchAssoc();
            if (!is_array($row)) {
                self::assertNull($row);
                self::assertSame(count(self::MESSAGES), $rowCount);
                break;
            }
            ++$rowCount;
            $dbgCtx = LoggableToString::convert(['$row' => $row, '$queryResult' => $queryResult]);
            $msgText = $row['text'];
            self::assertIsString($msgText);
            self::assertArrayHasKey($msgText, self::MESSAGES, $dbgCtx);
            self::assertEqualsEx(self::MESSAGES[$msgText], $row['time'], $dbgCtx);
        }
        $queryResult->close();

        if ($wrapInTx) {
            self::assertTrue($rollback ? $mySQLi->rollback() : $mySQLi->commit());
        }

        self::resetDbState($mySQLi, $queryKind);
        self::assertTrue($mySQLi->close());
    }

    /**
     * @dataProvider dataProviderForTestAutoInstrumentation
     */
    public function testAutoInstrumentation(MixedMap $testArgs): void
    {
        self::runAndEscalateLogLevelOnFailure(
            self::buildDbgDescForTestWithArtgs(__CLASS__, __FUNCTION__, $testArgs),
            function () use ($testArgs): void {
                $this->implTestAutoInstrumentation($testArgs);
            }
        );
    }

    private function implTestAutoInstrumentation(MixedMap $testArgs): void
    {
        TestCase::assertNotEmpty(self::MESSAGES);

        $logger = self::getLoggerStatic(__NAMESPACE__, __CLASS__, __FILE__);
        ($loggerProxy = $logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Entered', ['$testArgs' => $testArgs]);

        $disableInstrumentationsOptVal = $testArgs->getString(AutoInstrumentationUtilForTests::DISABLE_INSTRUMENTATIONS_KEY);
        $isInstrumentationEnabled = $testArgs->getBool(AutoInstrumentationUtilForTests::IS_INSTRUMENTATION_ENABLED_KEY);

        self::extractSharedArgs(
            $testArgs,
            /* out */ $isOOPApi,
            /* out */ $connectDbName,
            /* out */ $workDbName,
            /* out */ $queryKind,
            /* out */ $wrapInTx,
            /* out */ $rollback
        );

        $testCaseHandle = $this->getTestCaseHandle();

        $appCodeArgs = $testArgs->clone();

        $appCodeArgs[DbAutoInstrumentationUtilForTests::HOST_KEY] = AmbientContextForTests::testConfig()->mysqlHost;
        $appCodeArgs[DbAutoInstrumentationUtilForTests::PORT_KEY] = AmbientContextForTests::testConfig()->mysqlPort;
        $appCodeArgs[DbAutoInstrumentationUtilForTests::USER_KEY] = AmbientContextForTests::testConfig()->mysqlUser;
        $appCodeArgs[DbAutoInstrumentationUtilForTests::PASSWORD_KEY]
            = AmbientContextForTests::testConfig()->mysqlPassword;

        $expectationsBuilder = new MySQLiDbSpanDataExpectationsBuilder(self::DB_TYPE, $connectDbName, $isOOPApi);
        /** @var SpanExpectations[] $expectedSpans */
        $expectedSpans = [];
        if ($isInstrumentationEnabled) {
            $expectedSpans[] = $expectationsBuilder->fromNames('mysqli', '__construct', 'mysqli_connect');
            $expectedSpans[] = $expectationsBuilder->fromNames('mysqli', 'ping');

            if ($connectDbName !== $workDbName) {
                $expectedSpans[] = $expectationsBuilder->fromStatement(self::CREATE_DATABASE_IF_NOT_EXISTS_SQL_PREFIX . $workDbName);
                $expectationsBuilder = new MySQLiDbSpanDataExpectationsBuilder(self::DB_TYPE, $workDbName, $isOOPApi);
                $expectedSpans[] = $expectationsBuilder->fromNames('mysqli', 'select_db');
            }

            $expectedSpans[] = $expectationsBuilder->fromStatement(self::CREATE_TABLE_SQL);

            if ($wrapInTx) {
                $expectedSpans[] = $expectationsBuilder->fromNames('mysqli', 'begin_transaction');
            }

            foreach (self::MESSAGES as $ignored) {
                $expectedSpans[] = $expectationsBuilder->fromStatement(self::INSERT_SQL);
            }

            $expectedSpans[] = $expectationsBuilder->fromStatement(self::SELECT_SQL);

            if ($wrapInTx) {
                $expectedSpans[] = $expectationsBuilder->fromNames('mysqli', $rollback ? 'rollback' : 'commit');
            }

            self::addExpectationsForResetDbState($expectationsBuilder, $queryKind, /* out */ $expectedSpans);
        }

        $appCodeHost = $testCaseHandle->ensureMainAppCodeHost(
            function (AppCodeHostParams $appCodeParams) use ($disableInstrumentationsOptVal): void {
                if (!empty($disableInstrumentationsOptVal)) {
                    $appCodeParams->setAgentOption(OptionNames::DISABLE_INSTRUMENTATIONS, $disableInstrumentationsOptVal);
                }
                // Disable Span Compression feature to have all the expected spans individually
                $appCodeParams->setAgentOption(OptionNames::SPAN_COMPRESSION_ENABLED, false);
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
