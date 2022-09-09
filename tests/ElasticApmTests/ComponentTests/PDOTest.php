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

use Elastic\Apm\Impl\Log\LoggableToString;
use Elastic\Apm\Impl\Util\ClassNameUtil;
use ElasticApmTests\ComponentTests\Util\AppCodeRequestParams;
use ElasticApmTests\ComponentTests\Util\AppCodeTarget;
use ElasticApmTests\ComponentTests\Util\ComponentTestCaseBase;
use ElasticApmTests\ComponentTests\Util\ExpectedEventCounts;
use ElasticApmTests\ComponentTests\Util\TempFileUtilForTests;
use ElasticApmTests\Util\DbSpanDataExpectationsBuilder;
use ElasticApmTests\Util\SpanDataExpectations;
use ElasticApmTests\Util\SpanSequenceValidator;
use PDO;

final class PDOTest extends ComponentTestCaseBase
{
    private const DB_NAME_KEY = 'DB_NAME';
    private const WRAP_IN_TX_KEY = 'WRAP_IN_TX';
    private const MESSAGES_KEY = 'MESSAGES';

    private const CONNECTION_STRING_PREFIX = 'sqlite:';

    private const EXPECTED_DB_TYPE = 'sqlite';

    public const TEMP_DB_NAME = '<temporary database>';
    public const MEMORY_DB_NAME = 'memory';
    public const FILE_DB_NAME = '<file DB>';

    private const CREATE_TABLE_SQL
        = /** @lang text */
        'CREATE TABLE IF NOT EXISTS messages (
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

        // To create a database in memory, :memory: has to be appended to the DSN prefix
        yield [self::MEMORY_DB_NAME, 'sqlite::memory:'];

        // To create a database in memory, :memory: has to be appended to the DSN prefix
        yield ['/opt/databases/my_db.sqlite', 'sqlite:/opt/databases/my_db.sqlite'];

        // If the DSN consists of the DSN prefix only, a temporary database is used,
        // which is deleted when the connection is closed
        yield [self::TEMP_DB_NAME, 'sqlite:'];
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

    /**
     * @return iterable<array{array<string, mixed>}>
     */
    public function dataProviderForTest(): iterable
    {
        $dbNames = [];
        // TODO: Sergey Kleyman: UNCOMMENT
        // $dbNames[] = self::MEMORY_DB_NAME;
        // $dbNames[] = self::TEMP_DB_NAME;
        $dbNames[] = self::FILE_DB_NAME;
        foreach ($dbNames as $dbName) {
            foreach ([false, true] as $wrapInTx) {
                yield [[self::DB_NAME_KEY => $dbName, self::WRAP_IN_TX_KEY => $wrapInTx]];
            }
        }
    }

    /**
     * @param array<string, mixed> $appCodeArgs
     */
    public static function appCode(array $appCodeArgs): void
    {
        $dbName = self::getMandatoryAppCodeArg($appCodeArgs, self::DB_NAME_KEY);
        self::assertIsString($dbName);
        $wrapInTx = self::getMandatoryAppCodeArg($appCodeArgs, self::WRAP_IN_TX_KEY);
        self::assertIsBool($wrapInTx);
        $messages = self::getMandatoryAppCodeArg($appCodeArgs, self::MESSAGES_KEY);
        self::assertIsArray($messages);


        $pdo = new PDO(self::buildConnectionString($dbName));
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if ($wrapInTx) {
            $pdo->beginTransaction();
        }

        $pdo->exec(self::CREATE_TABLE_SQL);

        $stmt = $pdo->prepare(self::INSERT_SQL);
        self::assertNotFalse($stmt);
        $boundMsgText = '';
        $boundMsgTime = 0;
        $stmt->bindParam(':text', /* ref */ $boundMsgText);
        $stmt->bindParam(':time', /* ref */ $boundMsgTime);
        foreach ($messages as $msgText => $msgTime) {
            $boundMsgText = $msgText;
            $boundMsgTime = $msgTime;
            $stmt->execute();
        }

        $queryResult = $pdo->query(self::SELECT_SQL);
        self::assertNotFalse($queryResult);
        foreach ($queryResult as $row) {
            $dbgCtx = LoggableToString::convert(['$row' => $row, '$queryResult' => $queryResult]);
            $msgText = $row['text'];
            self::assertIsString($msgText);
            self::assertArrayHasKey($msgText, $messages, $dbgCtx);
            self::assertEquals($messages[$msgText], $row['time'], $dbgCtx);
        }

        if ($wrapInTx) {
            $pdo->commit();
        }
    }

    /**
     * @dataProvider dataProviderForTest
     *
     * @param array<string, mixed> $testArgs
     */
    public function test(array $testArgs): void
    {
        $dbNameArg = $testArgs[self::DB_NAME_KEY];
        self::assertIsString($dbNameArg);
        $wrapInTx = $testArgs[self::WRAP_IN_TX_KEY];
        self::assertIsBool($wrapInTx);

        $testCaseHandle = $this->getTestCaseHandle();

        $appCodeArgs = $testArgs;

        $dbName = $dbNameArg;
        if ($dbNameArg === self::FILE_DB_NAME) {
            $dbFileFullPath = TempFileUtilForTests::createTempFile(ClassNameUtil::fqToShort(__CLASS__) . '_temp_DB');
            $testCaseHandle->registerFileToDelete($dbFileFullPath);
            $dbName = $dbFileFullPath;
            $appCodeArgs[self::DB_NAME_KEY] = $dbName;
        }

        $messages = [
            'Just testing...'    => 1,
            'More testing...'    => 22,
            'SQLite3 is cool...' => 333,
        ];
        $appCodeArgs[self::MESSAGES_KEY] = $messages;

        $sharedExpectations = DbSpanDataExpectationsBuilder::default(self::EXPECTED_DB_TYPE, $dbName);
        $expectationsBuilder = new DbSpanDataExpectationsBuilder($sharedExpectations);
        /** @var SpanDataExpectations[] $expectedSpans */
        $expectedSpans = [];
        $expectedSpans[] = $expectationsBuilder->fromStatement(self::CREATE_TABLE_SQL);
        foreach ($messages as $ignored) {
            $expectedSpans[] = $expectationsBuilder->fromStatement(self::INSERT_SQL);
        }
        $expectedSpans[] = $expectationsBuilder->fromStatement(self::SELECT_SQL);

        $appCodeHost = $testCaseHandle->ensureMainAppCodeHost();
        $appCodeHost->sendRequest(
            AppCodeTarget::asRouted([__CLASS__, 'appCode']),
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
