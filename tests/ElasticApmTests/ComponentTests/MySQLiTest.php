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

use ElasticApmTests\ComponentTests\Util\AmbientContextForTests;
use ElasticApmTests\ComponentTests\Util\ComponentTestCaseBase;

/**
 * @group requires_external_services
 * @group requires_mysql_external_service
 */
final class MySQLiTest extends ComponentTestCaseBase
{
    public function testPrerequisitesSatisfied(): void
    {
        $extensionName = 'mysqli';
        self::assertTrue(extension_loaded($extensionName), 'Required extension ' . $extensionName . ' is not loaded');

        $logger = self::getLoggerStatic(__NAMESPACE__, __CLASS__, __FILE__);
        ($loggerProxy = $logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Entered', ['testConfig' => AmbientContextForTests::testConfig()]);

        self::assertNotNull(AmbientContextForTests::testConfig()->mysqlHost);
        self::assertNotNull(AmbientContextForTests::testConfig()->mysqlPort);
        self::assertNotFalse(filter_var(AmbientContextForTests::testConfig()->mysqlPort, FILTER_VALIDATE_INT));
        $portAsInt = intval(AmbientContextForTests::testConfig()->mysqlPort);
        self::assertNotNull(AmbientContextForTests::testConfig()->mysqlUser);
        self::assertNotNull(AmbientContextForTests::testConfig()->mysqlPassword);
        self::assertNotNull(AmbientContextForTests::testConfig()->mysqlDb);

        $mySQLi = mysqli_connect(
            AmbientContextForTests::testConfig()->mysqlHost,
            AmbientContextForTests::testConfig()->mysqlUser,
            AmbientContextForTests::testConfig()->mysqlPassword,
            AmbientContextForTests::testConfig()->mysqlDb,
            $portAsInt
        );
        self::assertNotFalse($mySQLi);
        self::assertTrue($mySQLi->ping());
    }
}
