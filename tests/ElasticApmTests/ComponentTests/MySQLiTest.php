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
use ElasticApmTests\ComponentTests\MySQLi\ApiFacade;
use ElasticApmTests\ComponentTests\MySQLi\ApiKind;
use ElasticApmTests\ComponentTests\MySQLi\MySQLiDbSpanDataExpectationsBuilder;
use ElasticApmTests\ComponentTests\Util\AmbientContextForTests;
use ElasticApmTests\ComponentTests\Util\AppCodeHostParams;
use ElasticApmTests\ComponentTests\Util\AppCodeRequestParams;
use ElasticApmTests\ComponentTests\Util\AppCodeTarget;
use ElasticApmTests\ComponentTests\Util\ComponentTestCaseBase;
use ElasticApmTests\ComponentTests\Util\ExpectedEventCounts;
use ElasticApmTests\Util\DbSpanDataExpectationsBuilder;
use ElasticApmTests\Util\SpanDataExpectations;
use ElasticApmTests\Util\SpanSequenceValidator;

/**
 * @group requires_external_services
 * @group requires_mysql_external_service
 */
final class MySQLiTest extends ComponentTestCaseBase
{
    private const DISABLE_INSTRUMENTATIONS_KEY = 'DISABLE_INSTRUMENTATIONS';
    private const IS_INSTRUMENTATION_ENABLED_KEY = 'IS_INSTRUMENTATION_ENABLED';
    private const API_KIND_KEY = 'API_KIND';
    private const HOST_KEY = 'HOST';
    private const PORT_KEY = 'PORT';
    private const USER_KEY = 'USER';
    private const PASSWORD_KEY = 'PASSWORD';
    private const DB_NAME_KEY = 'DB_NAME';
    private const USE_SELECT_DB_KEY = 'USE_SELECT_DB';
    private const WRAP_IN_TX_KEY = 'WRAP_IN_TX';
    private const MESSAGES_KEY = 'MESSAGES';

    private const DB_TYPE = 'mysql';

    // private const DROP_DATABASE_IF_EXISTS_SQL_PREFIX
    //     = /** @lang text */
    //     'DROP DATABASE IF EXISTS ';
    //
    // private const CREATE_DATABASE_SQL_PREFIX
    //     = /** @lang text */
    //     'CREATE DATABASE ';

    // private const CREATE_TABLE_SQL
    //     = /** @lang text */
    //     'CREATE TABLE messages (
    //         id INTEGER PRIMARY KEY,
    //         text TEXT,
    //         time INTEGER)';
    //
    // private const INSERT_SQL
    //     = /** @lang text */
    //     'INSERT INTO messages (text, time) VALUES (:text, :time)';
    //
    // private const SELECT_SQL
    //     = /** @lang text */
    //     'SELECT * FROM messages';

    public function testPrerequisitesSatisfied(): void
    {
        $extensionName = 'mysqli';
        self::assertTrue(extension_loaded($extensionName), 'Required extension ' . $extensionName . ' is not loaded');

        self::assertNotNull(AmbientContextForTests::testConfig()->mysqlHost);
        self::assertNotNull(AmbientContextForTests::testConfig()->mysqlPort);
        self::assertNotNull(AmbientContextForTests::testConfig()->mysqlUser);
        self::assertNotNull(AmbientContextForTests::testConfig()->mysqlPassword);
        self::assertNotNull(AmbientContextForTests::testConfig()->mysqlDb);
    }

    public function testIsAutoInstrumentationEnabled(): void
    {
        $this->implTestIsAutoInstrumentationEnabled(
            MySQLiAutoInstrumentation::class /* <- instrClass */,
            ['mysqli', 'db'] /* <- expectedNames */
        );
    }

    /**
     * @return iterable<array{array<string, mixed>}>
     */
    public function dataProviderForTestAutoInstrumentation(): iterable
    {
        $disableInstrumentationsVariants = [
            null     => true,
            'mysqli' => false,
            'db'     => false,
        ];

        /**
         * @param mixed[] $variants
         *
         * @return string[]
         */
        $onlyIfEnabled = function (array $variants, bool $isEnabled): array {
            return $isEnabled ? $variants : [$variants[0]];
        };

        // TODO: Sergey Kleyman: UNCOMMENT
        // foreach ($disableInstrumentationsVariants as $disableInstrumentationsOptVal => $isInstrumentationEnabled) {
        foreach ([null => true] as $disableInstrumentationsOptVal => $isInstrumentationEnabled) {
            // TODO: Sergey Kleyman: UNCOMMENT
            // foreach ($onlyIfEnabled(MySQLiApiKind::allValues(), $isInstrumentationEnabled) as $apiKind) {
            foreach ([ApiKind::procedural()] as $apiKind) {
                // TODO: Sergey Kleyman: UNCOMMENT
                // foreach ($onlyIfEnabled([false, true], $isInstrumentationEnabled) as $wrapInTx) {
                foreach ([false] as $wrapInTx) {
                    // TODO: Sergey Kleyman: UNCOMMENT
                    // foreach ($onlyIfEnabled([false, true], $isInstrumentationEnabled) as $useSelectDb) {
                    foreach ([false] as $useSelectDb) {
                        yield [
                            [
                                self::DISABLE_INSTRUMENTATIONS_KEY   => $disableInstrumentationsOptVal,
                                self::IS_INSTRUMENTATION_ENABLED_KEY => $isInstrumentationEnabled,
                                self::API_KIND_KEY                   => $apiKind,
                                self::USE_SELECT_DB_KEY              => $useSelectDb,
                                self::WRAP_IN_TX_KEY                 => $wrapInTx,
                            ],
                        ];
                    }
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $appCodeArgs
     */
    public static function appCodeForTestAutoInstrumentation(array $appCodeArgs): void
    {
        $apiKindAsString = self::getMandatoryAppCodeArg($appCodeArgs, self::API_KIND_KEY);
        self::assertIsString($apiKindAsString);
        $apiKind = ApiKind::fromString($apiKindAsString);
        $host = self::getMandatoryAppCodeArg($appCodeArgs, self::HOST_KEY);
        self::assertIsString($host);
        $port = self::getMandatoryAppCodeArg($appCodeArgs, self::PORT_KEY);
        self::assertIsInt($port);
        $user = self::getMandatoryAppCodeArg($appCodeArgs, self::USER_KEY);
        self::assertIsString($user);
        $password = self::getMandatoryAppCodeArg($appCodeArgs, self::PASSWORD_KEY);
        self::assertIsString($password);
        $dbName = self::getMandatoryAppCodeArg($appCodeArgs, self::DB_NAME_KEY);
        self::assertIsString($dbName);

        $useSelectDb = self::getMandatoryAppCodeArg($appCodeArgs, self::USE_SELECT_DB_KEY);
        self::assertIsBool($useSelectDb);
        $wrapInTx = self::getMandatoryAppCodeArg($appCodeArgs, self::WRAP_IN_TX_KEY);
        self::assertIsBool($wrapInTx);
        $messages = self::getMandatoryAppCodeArg($appCodeArgs, self::MESSAGES_KEY);
        self::assertIsArray($messages);

        $mySQLiApiFacade = new ApiFacade($apiKind);
        $mySQLi = $mySQLiApiFacade->connect($host, $port, $user, $password, $useSelectDb ? null : $dbName);
        self::assertNotNull($mySQLi);
        self::assertTrue($mySQLi->ping());

        // self::assertNotFalse($mySQLi->query(self::DROP_DATABASE_IF_EXISTS_SQL_PREFIX . $dbName));
        // self::assertNotFalse($mySQLi->query(self::CREATE_DATABASE_SQL_PREFIX . $dbName));
        //
        // if ($useSelectDb) {
        //     self::assertTrue($mySQLi->selectDb($dbName));
        // }

        // self::assertTrue($mySQLi->query());

        // $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // if ($wrapInTx) {
        //     $pdo->beginTransaction();
        // }
        //
        // $pdo->exec(self::CREATE_TABLE_SQL);
        //
        // $stmt = $pdo->prepare(self::INSERT_SQL);
        // self::assertNotFalse($stmt);
        // $boundMsgText = '';
        // $boundMsgTime = 0;
        // $stmt->bindParam(':text', /* ref */ $boundMsgText);
        // $stmt->bindParam(':time', /* ref */ $boundMsgTime);
        // foreach ($messages as $msgText => $msgTime) {
        //     $boundMsgText = $msgText;
        //     $boundMsgTime = $msgTime;
        //     $stmt->execute();
        // }
        //
        // $queryResult = $pdo->query(self::SELECT_SQL);
        // self::assertNotFalse($queryResult);
        // foreach ($queryResult as $row) {
        //     $dbgCtx = LoggableToString::convert(['$row' => $row, '$queryResult' => $queryResult]);
        //     $msgText = $row['text'];
        //     self::assertIsString($msgText);
        //     self::assertArrayHasKey($msgText, $messages, $dbgCtx);
        //     self::assertEquals($messages[$msgText], $row['time'], $dbgCtx);
        // }
        //
        // if ($wrapInTx) {
        //     $pdo->commit();
        // }
    }

    /**
     * @dataProvider dataProviderForTestAutoInstrumentation
     *
     * @param array<string, mixed> $testArgs
     */
    public function testAutoInstrumentation(array $testArgs): void
    {
        $logger = self::getLoggerStatic(__NAMESPACE__, __CLASS__, __FILE__);
        ($loggerProxy = $logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Entered', ['$testArgs' => $testArgs]);

        $disableInstrumentationsOptVal = self::getMandatoryAppCodeArg($testArgs, self::DISABLE_INSTRUMENTATIONS_KEY);
        if ($disableInstrumentationsOptVal !== null) {
            self::assertIsString($disableInstrumentationsOptVal);
        }
        $isInstrumentationEnabled = self::getMandatoryAppCodeArg($testArgs, self::IS_INSTRUMENTATION_ENABLED_KEY);
        self::assertIsBool($isInstrumentationEnabled);
        $apiKind = $testArgs[self::API_KIND_KEY];
        self::assertInstanceOf(ApiKind::class, $apiKind);
        $useSelectDb = $testArgs[self::USE_SELECT_DB_KEY];
        self::assertIsBool($useSelectDb);
        $wrapInTx = $testArgs[self::WRAP_IN_TX_KEY];
        self::assertIsBool($wrapInTx);

        $testCaseHandle = $this->getTestCaseHandle();

        $appCodeArgs = $testArgs;

        $appCodeArgs[self::API_KIND_KEY] = $apiKind->asString();

        $appCodeArgs[self::HOST_KEY] = AmbientContextForTests::testConfig()->mysqlHost;
        $appCodeArgs[self::PORT_KEY] = AmbientContextForTests::testConfig()->mysqlPort;
        $appCodeArgs[self::USER_KEY] = AmbientContextForTests::testConfig()->mysqlUser;
        $appCodeArgs[self::PASSWORD_KEY] = AmbientContextForTests::testConfig()->mysqlPassword;
        $dbName = AmbientContextForTests::testConfig()->mysqlDb;
        self::assertNotNull($dbName);
        if ($useSelectDb) {
            $dbName .= '-2';
        }
        $appCodeArgs[self::DB_NAME_KEY] = $dbName;

        $messages = [
            'Just testing...'    => 1,
            'More testing...'    => 22,
            'SQLite3 is cool...' => 333,
        ];
        $appCodeArgs[self::MESSAGES_KEY] = $messages;

        $sharedExpectations
            = DbSpanDataExpectationsBuilder::default(self::DB_TYPE, $useSelectDb ? null : $dbName);
        $expectationsBuilder = new MySQLiDbSpanDataExpectationsBuilder($apiKind, $sharedExpectations);
        /** @var SpanDataExpectations[] $expectedSpans */
        $expectedSpans = [];
        if ($isInstrumentationEnabled) {
            $expectedSpans[] = $expectationsBuilder->fromFuncName('mysqli', '__construct', 'mysqli_connect');
            $expectedSpans[] = $expectationsBuilder->fromFuncName('mysqli', 'ping');

            // $expectedSpans[]
            //     = $expectationsBuilder->fromStatement(self::DROP_DATABASE_IF_EXISTS_SQL_PREFIX . $dbName);
            // $expectedSpans[] = $expectationsBuilder->fromStatement(self::CREATE_DATABASE_SQL_PREFIX . $dbName);
            // if ($useSelectDb) {
            //     $expectationsBuilder->setPrototype(
            //         DbSpanDataExpectationsBuilder::default(self::DB_TYPE, $dbName)
            //     );
            //     $expectedSpans[] = $expectationsBuilder->fromFuncName('mysqli', 'select_db');
            // }

            // $expectedSpans[] = $expectationsBuilder->fromStatement(self::CREATE_TABLE_SQL);
            // foreach ($messages as $ignored) {
            //     $expectedSpans[] = $expectationsBuilder->fromStatement(self::INSERT_SQL);
            // }
            // $expectedSpans[] = $expectationsBuilder->fromStatement(self::SELECT_SQL);
        }

        $appCodeHost = $testCaseHandle->ensureMainAppCodeHost(
            function (AppCodeHostParams $appCodeParams) use ($disableInstrumentationsOptVal): void {
                if ($disableInstrumentationsOptVal !== null) {
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
