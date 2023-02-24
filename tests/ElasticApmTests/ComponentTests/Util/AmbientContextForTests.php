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

use Elastic\Apm\Impl\Clock;
use Elastic\Apm\Impl\Config\RawSnapshotSourceInterface;
use Elastic\Apm\Impl\Log\Backend as LogBackend;
use Elastic\Apm\Impl\Log\Level as LogLevel;
use Elastic\Apm\Impl\Log\LoggerFactory;
use ElasticApmTests\Util\LogSinkForTests;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AmbientContextForTests
{
    /** @var ?self */
    private static $singletonInstance = null;

    /** @var string */
    private $dbgProcessName;

    /** @var LogBackend */
    private $logBackend;

    /** @var LoggerFactory */
    private $loggerFactory;

    /** @var ConfigSnapshotForTests */
    private $testConfig;

    /** @var Clock */
    private $clock;

    private function __construct(string $dbgProcessName)
    {
        $this->dbgProcessName = $dbgProcessName;
        $this->logBackend = new LogBackend(LogLevel::ERROR, new LogSinkForTests($dbgProcessName));
        $this->loggerFactory = new LoggerFactory($this->logBackend);
        $this->clock = new Clock($this->loggerFactory);
        $this->readAndApplyConfig(/* additionalConfigSource */ null);
    }

    public static function init(string $dbgProcessName): void
    {
        if (self::$singletonInstance !== null) {
            TestCase::assertSame(self::$singletonInstance->dbgProcessName, $dbgProcessName);
            return;
        }

        self::$singletonInstance = new self($dbgProcessName);

        if (self::testConfig()->appCodePhpIni !== null && !file_exists(self::testConfig()->appCodePhpIni)) {
            $optionName = AllComponentTestsOptionsMetadata::APP_CODE_PHP_INI_OPTION_NAME;
            $envVarName = ConfigUtilForTests::testOptionNameToEnvVarName($optionName);
            throw new RuntimeException(
                "Option $optionName (environment variable $envVarName)"
                . ' is set but it points to a file that does not exist: '
                . self::testConfig()->appCodePhpIni
            );
        }
    }

    private static function getSingletonInstance(): self
    {
        TestCase::assertNotNull(self::$singletonInstance);
        return self::$singletonInstance;
    }

    public static function reconfigure(?RawSnapshotSourceInterface $additionalConfigSource = null): void
    {
        self::getSingletonInstance()->readAndApplyConfig($additionalConfigSource);
    }

    private function readAndApplyConfig(?RawSnapshotSourceInterface $additionalConfigSource): void
    {
        $this->testConfig = ConfigUtilForTests::read($additionalConfigSource, $this->loggerFactory);
        $this->logBackend->setMaxEnabledLevel($this->testConfig->logLevel);
    }

    public static function resetLogLevel(int $newVal): void
    {
        self::resetConfigOption(
            AllComponentTestsOptionsMetadata::LOG_LEVEL_OPTION_NAME,
            $newVal,
            LogLevel::intToName($newVal)
        );
        Assert::assertSame($newVal, AmbientContextForTests::testConfig()->logLevel);
    }

    public static function resetEscalatedRerunsMaxCount(int $newVal): void
    {
        self::resetConfigOption(
            AllComponentTestsOptionsMetadata::ESCALATED_RERUNS_MAX_COUNT_OPTION_NAME,
            $newVal,
            strval($newVal)
        );
        Assert::assertSame($newVal, AmbientContextForTests::testConfig()->escalatedRerunsMaxCount);
    }

    /**
     * @param string $optName
     * @param mixed $newVal
     * @param string $newValAsEnvVar
     *
     * @return void
     */
    private static function resetConfigOption(string $optName, $newVal, string $newValAsEnvVar): void
    {
        $envVarName = ConfigUtilForTests::testOptionNameToEnvVarName($optName);
        EnvVarUtilForTests::set($envVarName, $newValAsEnvVar);
        AmbientContextForTests::reconfigure();
    }

    public static function dbgProcessName(): string
    {
        return self::getSingletonInstance()->dbgProcessName;
    }

    public static function testConfig(): ConfigSnapshotForTests
    {
        return self::getSingletonInstance()->testConfig;
    }

    public static function loggerFactory(): LoggerFactory
    {
        return self::getSingletonInstance()->loggerFactory;
    }

    public static function clock(): Clock
    {
        return self::getSingletonInstance()->clock;
    }
}
