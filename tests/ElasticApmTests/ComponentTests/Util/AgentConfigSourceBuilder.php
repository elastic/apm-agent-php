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
use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableTrait;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Util\ArrayUtil;
use Elastic\Apm\Impl\Util\ExceptionUtil;
use ElasticApmTests\Util\LogCategoryForTests;
use RuntimeException;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class AgentConfigSourceBuilder implements LoggableInterface
{
    use LoggableTrait;

    /** @var Logger */
    private $logger;

    /** @var ResourcesClient */
    private $resourcesClient;

    /** @var AppCodeHostParams */
    private $appCodeHostParams;

    /** @var ?string */
    private $tempIniFileFullPath = null;

    public function __construct(ResourcesClient $resourcesClient, AppCodeHostParams $appCodeHostParams)
    {
        $this->logger = AmbientContextForTests::loggerFactory()->loggerForClass(
            LogCategoryForTests::TEST_UTIL,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        )->addContext('this', $this);

        $this->resourcesClient = $resourcesClient;
        $this->appCodeHostParams = $appCodeHostParams;
    }

    /**
     * @param array<string, string> $envVars
     *
     * @return array<string, string>
     */
    private function addEnvVarsForAgentOptions(array $envVars): array
    {
        $result = $envVars;
        $agentOptionsToEnvVars = $this->appCodeHostParams->getAgentOptions(AgentConfigSourceKind::envVars());
        foreach ($agentOptionsToEnvVars as $optName => $optVal) {
            $result[ConfigUtilForTests::agentOptionNameToEnvVarName($optName)]
                = ConfigUtilForTests::optionValueToString($optVal);
        }
        return $result;
    }

    /**
     * @param array<string, string> $baseEnvVars
     *
     * @return array<string, string>
     */
    public function getEnvVars(array $baseEnvVars): array
    {
        return $this->addEnvVarsForAgentOptions($this->appCodeHostParams->selectEnvVarsToInherit($baseEnvVars));
    }

    /**
     * @return string Path to built .ini file
     */
    public function getPhpIniFile(): ?string
    {
        if (ArrayUtil::isEmpty($this->appCodeHostParams->getAgentOptions(AgentConfigSourceKind::iniFile()))) {
            return AmbientContextForTests::testConfig()->appCodePhpIni;
        }

        return $this->ensureTempIniFileCreated();
    }

    /**
     * @param resource $handle
     * @param string   $str
     */
    private static function writeToFile($handle, string $str): void
    {
        if (!fwrite($handle, $str)) {
            throw new RuntimeException('Failed to write to file');
        }
    }

    /**
     * @param string|int|float|bool $optVal
     *
     * @return string
     */
    private static function processOptionValueBeforeWriteToIni($optVal): string
    {
        if (!is_string($optVal)) {
            return ConfigUtilForTests::optionValueToString($optVal);
        }

        /**
         * If the string contains a non-word characters (not a letter, number, underscore) than return it in quotes.
         *
         * \W     Any non-word character. A word character is a letter, number, underscore.
         *
         * @link https://www.php.net/manual/en/function.preg-match.php
         */
        return preg_match('/\W/', $optVal) ? ('"' . $optVal . '"') : $optVal;
    }

    /**
     * @param resource $tempIniFileHandle
     * @param ?string  $baseIniFileFullPath
     */
    private function writeOptionsTempIni($tempIniFileHandle, ?string $baseIniFileFullPath): void
    {
        if ($baseIniFileFullPath === null) {
            $baseIniContent = [];
        } else {
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
        }

        self::writeToFile($tempIniFileHandle, PHP_EOL);
        self::writeToFile($tempIniFileHandle, '[elastic_apm]' . PHP_EOL);

        $optionsForIni = $this->appCodeHostParams->getAgentOptions(AgentConfigSourceKind::iniFile());
        foreach ($optionsForIni as $optName => $optVal) {
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
        $baseIniFileFullPath = AmbientContextForTests::testConfig()->appCodePhpIni ?? php_ini_loaded_file();
        if ($baseIniFileFullPath === false) {
            $baseIniFileFullPath = null;
        } else {
            if (!copy($baseIniFileFullPath, $tempIniFileFullPath)) {
                throw new RuntimeException(
                    ExceptionUtil::buildMessage(
                        'Failed to create a copy of base INI file as temporary INI file',
                        ['baseIniFileFullPath' => $baseIniFileFullPath, 'tempIniFileFullPath' => $tempIniFileFullPath]
                    )
                );
            }
        }

        /* w - write from scratch | a - appended */
        $mode = $baseIniFileFullPath === null ? 'w' : 'a';
        $tempIniFileHandle = fopen($tempIniFileFullPath, $mode);
        if ($tempIniFileHandle === false) {
            throw new RuntimeException(
                ExceptionUtil::buildMessage(
                    'Failed to open temporary INI file for writing',
                    ['tempIniFileFullPath' => $tempIniFileFullPath]
                )
            );
        }

        try {
            $isWriteSuccessful = false;
            $this->writeOptionsTempIni($tempIniFileHandle, $baseIniFileFullPath);
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

    private function ensureTempIniFileCreated(): string
    {
        if ($this->tempIniFileFullPath !== null) {
            return $this->tempIniFileFullPath;
        }

        $shouldBeDeletedOnTestExit = AmbientContextForTests::testConfig()->deleteTempPhpIni;
        $tempIniFileFullPath = $this->resourcesClient->createTempFile('php_ini', $shouldBeDeletedOnTestExit);

        $this->writeTempIniContent($tempIniFileFullPath);

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Exiting', ['tempIniFileFullPath' => $tempIniFileFullPath]);

        return $tempIniFileFullPath;
    }
}
