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

use Elastic\Apm\Impl\Config\RawSnapshotSourceInterface;
use Elastic\Apm\Impl\Log\Backend as LogBackend;
use Elastic\Apm\Impl\Log\LoggerFactory;
use ElasticApmTests\Util\LogSinkForTests;
use ElasticApmTests\Util\TestCaseBase;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AmbientContext
{
    /** @var self */
    private static $singletonInstance;

    /** @var string */
    private $dbgProcessName;

    /** @var LoggerFactory */
    private $loggerFactory;

    /** @var TestConfigSnapshot */
    private $testConfig;

    private function __construct(string $dbgProcessName)
    {
        $this->dbgProcessName = $dbgProcessName;
        $this->readAndApplyConfig(/* additionalConfigSource */ null);
    }

    public static function init(string $dbgProcessName): void
    {
        if (!isset(self::$singletonInstance)) {
            self::$singletonInstance = new AmbientContext($dbgProcessName);
        }

        if (self::testConfig()->appCodeHostKind === AppCodeHostKind::NOT_SET) {
            $optionName = AllComponentTestsOptionsMetadata::APP_CODE_HOST_KIND_OPTION_NAME;
            $envVarName = TestConfigUtil::envVarNameForTestOption($optionName);
            throw new RuntimeException(
                'Required configuration option ' . $optionName
                . " (environment variable $envVarName)" . ' is not set'
            );
        }

        if (!is_null(self::testConfig()->appCodePhpIni) && !file_exists(self::testConfig()->appCodePhpIni)) {
            $optionName = AllComponentTestsOptionsMetadata::APP_CODE_PHP_INI_OPTION_NAME;
            $envVarName = TestConfigUtil::envVarNameForTestOption($optionName);
            throw new RuntimeException(
                "Option $optionName (environment variable $envVarName)"
                . ' is set but it points to a file that does not exist: '
                . self::testConfig()->appCodePhpIni
            );
        }

        TestCaseBase::$isUnitTest = false;
    }

    public static function reconfigure(RawSnapshotSourceInterface $additionalConfigSource): void
    {
        TestCase::assertTrue(isset(self::$singletonInstance));
        self::$singletonInstance->readAndApplyConfig($additionalConfigSource);
    }

    private function readAndApplyConfig(?RawSnapshotSourceInterface $additionalConfigSource): void
    {
        $this->testConfig = TestConfigUtil::read($this->dbgProcessName, $additionalConfigSource);
        $this->loggerFactory = new LoggerFactory(
            new LogBackend(
                $this->testConfig->logLevel,
                new LogSinkForTests($this->dbgProcessName)
            )
        );
    }

    public static function dbgProcessName(): string
    {
        TestCase::assertTrue(isset(self::$singletonInstance));

        return self::$singletonInstance->dbgProcessName;
    }

    public static function testConfig(): TestConfigSnapshot
    {
        TestCase::assertTrue(isset(self::$singletonInstance));

        return self::$singletonInstance->testConfig;
    }

    public static function loggerFactory(): LoggerFactory
    {
        TestCase::assertTrue(isset(self::$singletonInstance));

        return self::$singletonInstance->loggerFactory;
    }
}
