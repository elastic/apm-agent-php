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

use Elastic\Apm\Impl\Config\IniRawSnapshotSource;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Util\ExceptionUtil;
use ElasticApmTests\Util\LogCategoryForTests;
use RuntimeException;

final class AgentConfigSetterIni extends AgentConfigSetter
{
    /** @var ?string */
    private $tempIniFileFullPath = null;

    /** @var Logger */
    private $logger;

    public function __construct()
    {
        $this->logger = AmbientContext::loggerFactory()->loggerForClass(
            LogCategoryForTests::TEST_UTIL,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        );
    }

    public function appCodePhpCmd(): string
    {
        $this->createTempIniFile();

        return self::buildAppCodePhpCmd($this->tempIniFileFullPath);
    }

    public function additionalEnvVars(): array
    {
        return [];
    }

    public function tearDown(): void
    {
        if (AmbientContext::testConfig()->deleteTempPhpIni && $this->tempIniFileFullPath !== null) {
            if (!unlink($this->tempIniFileFullPath)) {
                throw new RuntimeException(
                    ExceptionUtil::buildMessage(
                        'Failed to delete temporary INI file',
                        ['tempIniFileFullPath' => $this->tempIniFileFullPath]
                    )
                );
            }
        }
    }

    /**
     * @param resource $handle
     * @param string $str
     */
    private static function writeToFile($handle, string $str): void
    {
        if (!fwrite($handle, $str)) {
            throw new RuntimeException('Failed to write to file');
        }
    }

    private static function processOptionValueBeforeWriteToIni(string $optVal): string
    {
        if (!preg_match('/[^\w]/', $optVal)) {
            return $optVal;
        }

        return '"' . $optVal . '"';
    }

    /**
     * @param resource $tempIniFileHandle
     * @param string   $baseIniFileFullPath
     */
    private function appendOptionsTempIni($tempIniFileHandle, string $baseIniFileFullPath): void
    {
        $baseIniContent = parse_ini_file(
            $baseIniFileFullPath,
            /* process_sections: */ false,
            /* scanner_mode: */ INI_SCANNER_RAW
        );
        if ($baseIniContent === false) {
            throw new RuntimeException(
                ExceptionUtil::buildMessage(
                    'Failed to parse the base INI file',
                    ['baseIniFileFullPath' => $baseIniFileFullPath]
                )
            );
        }

        self::writeToFile($tempIniFileHandle, PHP_EOL);
        self::writeToFile($tempIniFileHandle, '[elastic_apm]' . PHP_EOL);

        foreach ($this->optionNameToValue as $optName => $optVal) {
            $optIniName
                = IniRawSnapshotSource::optionNameToIniName(IniRawSnapshotSource::DEFAULT_PREFIX, $optName);

            if (array_key_exists($optIniName, $baseIniContent)) {
                throw new RuntimeException(
                    ExceptionUtil::buildMessage(
                        'Option already present in base INI file',
                        ['optIniName' => $optIniName, 'baseIniFileFullPath' => $baseIniFileFullPath]
                    )
                );
            }

            self::writeToFile(
                $tempIniFileHandle,
                $optIniName . ' = ' . self::processOptionValueBeforeWriteToIni($optVal) . PHP_EOL
            );
        }
    }

    private function writeTempIniContent(string $tempIniFileFullPath): void
    {
        $baseIniFileFullPath = AmbientContext::testConfig()->appCodePhpIni ?? php_ini_loaded_file();
        if ($baseIniFileFullPath === false) {
            throw new RuntimeException('Failed to find the base INI file');
        }

        if (!copy($baseIniFileFullPath, $tempIniFileFullPath)) {
            throw new RuntimeException(
                ExceptionUtil::buildMessage(
                    'Failed to create a copy of base INI file as temporary INI file',
                    ['baseIniFileFullPath' => $baseIniFileFullPath, 'tempIniFileFullPath' => $tempIniFileFullPath]
                )
            );
        }

        $tempIniFileHandle = fopen($tempIniFileFullPath, /* mode: writes are always appended */ 'a');
        if ($tempIniFileHandle === false) {
            throw new RuntimeException(
                ExceptionUtil::buildMessage(
                    'Failed to open temporary INI file for writing',
                    ['tempIniFileFullPath' => $tempIniFileFullPath]
                )
            );
        }

        try {
            /** @noinspection PhpUnusedLocalVariableInspection */
            $isWriteSuccessful = false;
            $this->appendOptionsTempIni($tempIniFileHandle, $baseIniFileFullPath);
            $isWriteSuccessful = true;
        } finally {
            if (!fclose($tempIniFileHandle)) {
                if ($isWriteSuccessful) {
                    throw new RuntimeException(
                        ExceptionUtil::buildMessage(
                            'Failed to close temporary INI file after writing',
                            ['tempIniFileFullPath' => $tempIniFileFullPath]
                        )
                    );
                } else {
                    ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
                    && $loggerProxy->log(
                        'Failed to close temporary INI file after writing',
                        ['tempIniFileFullPath' => $tempIniFileFullPath]
                    );
                }
            }
        }
    }

    private function createTempIniFile(): void
    {
        $tempIniFileFullPath = tempnam(
            sys_get_temp_dir(),
            /* prefix: */ 'Elastic_APM_PHP_Agent_component_tests_php_ini_'
        );
        if ($tempIniFileFullPath === false) {
            throw new RuntimeException(
                ExceptionUtil::buildMessage(
                    'Failed to create a temporary INI file',
                    ['sys_get_temp_dir' => sys_get_temp_dir()]
                )
            );
        }

        $this->writeTempIniContent($tempIniFileFullPath);

        $this->tempIniFileFullPath = $tempIniFileFullPath;

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Exiting', ['tempIniFileFullPath' => $tempIniFileFullPath]);
    }
}
