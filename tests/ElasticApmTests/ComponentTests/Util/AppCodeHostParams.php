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

use Elastic\Apm\Impl\Config\AllOptionsMetadata;
use Elastic\Apm\Impl\Config\CompositeRawSnapshotSource;
use Elastic\Apm\Impl\Config\EnvVarsRawSnapshotSource;
use Elastic\Apm\Impl\Config\Parser as ConfigParser;
use Elastic\Apm\Impl\Config\Snapshot as AgentConfigSnapshot;
use Elastic\Apm\Impl\Log\Level as LogLevel;
use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableTrait;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Log\LoggerFactory;
use Elastic\Apm\Impl\Util\ArrayUtil;
use Elastic\Apm\Impl\Util\TextUtil;
use ElasticApmTests\UnitTests\Util\MockConfigRawSnapshotSource;
use ElasticApmTests\Util\IterableUtilForTests;
use ElasticApmTests\Util\LogCategoryForTests;
use ElasticApmTests\Util\RandomUtilForTests;
use PHPUnit\Framework\Assert;

class AppCodeHostParams implements LoggableInterface
{
    use LoggableTrait;

    /** @var string */
    public $dbgProcessName;

    /** @var AgentConfigSourceKind */
    private $defaultAgentConfigSource;

    /** @var array<string, array<string, string|int|float|bool>> */
    private $agentOptions = [];

    /** @var string */
    public $spawnedProcessInternalId;

    /** @var Logger */
    private $logger;

    public function __construct(string $dbgProcessName)
    {
        $this->logger = AmbientContextForTests::loggerFactory()->loggerForClass(
            LogCategoryForTests::TEST_UTIL,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        )->addContext('this', $this);

        $this->dbgProcessName = $dbgProcessName;

        $this->defaultAgentConfigSource = RandomUtilForTests::getRandomValueFromArray(AgentConfigSourceKind::all());
        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Randomly selected default agent config source',
            ['defaultAgentConfigSource' => $this->defaultAgentConfigSource, 'dbgProcessName' => $this->dbgProcessName]
        );
    }

    public function setDefaultAgentConfigSource(AgentConfigSourceKind $defaultAgentConfigSource): self
    {
        $this->defaultAgentConfigSource = $defaultAgentConfigSource;

        return $this;
    }

    /**
     * @param string                 $optName
     * @param string|int|float|bool  $optVal
     * @param ?AgentConfigSourceKind $sourceKind
     */
    public function setAgentOption(string $optName, $optVal, ?AgentConfigSourceKind $sourceKind = null): void
    {
        $sourceKindAsString = ($sourceKind ?? $this->defaultAgentConfigSource)->asString();
        if (!array_key_exists($sourceKindAsString, $this->agentOptions)) {
            $this->agentOptions[$sourceKindAsString] = [];
        }
        $this->agentOptions[$sourceKindAsString][$optName] = $optVal;
    }

    /**
     * @param array<string, string> $input
     *
     * @return array<string, string>
     */
    private function removeLogLevelEnvVarsIfSetByOptions(array $input): array
    {
        $isAnyLogLevelOptionsSet = false;
        foreach ($this->getExplicitlySetAgentOptionsNames() as $optName) {
            if (ConfigUtilForTests::isOptionLogLevelRelated($optName)) {
                $isAnyLogLevelOptionsSet = true;
                break;
            }
        }
        if (!$isAnyLogLevelOptionsSet) {
            return $input;
        }

        $output = $input;
        foreach (ConfigUtilForTests::allAgentLogLevelRelatedOptionNames() as $optName) {
            $envVarName = ConfigUtilForTests::agentOptionNameToEnvVarName($optName);
            if (array_key_exists($envVarName, $output)) {
                unset($output[$envVarName]);
            }
        }

        return $output;
    }

    /**
     * @param array<string, string> $baseEnvVars
     *
     * @return array<string, string>
     */
    public function selectEnvVarsToInherit(array $baseEnvVars): array
    {
        $envVars = $baseEnvVars;

        $envVars = $this->removeLogLevelEnvVarsIfSetByOptions($envVars);

        foreach ($this->getExplicitlySetAgentOptionsNames() as $optName) {
            $envVarName = ConfigUtilForTests::agentOptionNameToEnvVarName($optName);
            if (array_key_exists($envVarName, $envVars)) {
                unset($envVars[$envVarName]);
            }
        }

        return array_filter(
            $envVars,
            function (string $envVarName): bool {
                // Return false for entries to be removed

                // Keep environment variables related to testing infrastructure
                if (TextUtil::isPrefixOfIgnoreCase(ConfigUtilForTests::ENV_VAR_NAME_PREFIX, $envVarName)) {
                    return true;
                }

                // Keep environment variables related to agent's logging
                if (
                    TextUtil::isPrefixOfIgnoreCase(
                        EnvVarsRawSnapshotSource::DEFAULT_NAME_PREFIX . 'LOG_',
                        $envVarName
                    )
                ) {
                    return true;
                }

                // Keep environment variables explicitly configured to be passed through
                if (AmbientContextForTests::testConfig()->isEnvVarToPassThrough($envVarName)) {
                    return true;
                }

                // Keep environment variables NOT related to Elastic APM
                if (!TextUtil::isPrefixOfIgnoreCase(EnvVarsRawSnapshotSource::DEFAULT_NAME_PREFIX, $envVarName)) {
                    return true;
                }

                return false;
            },
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * @return iterable<string>
     */
    private function getExplicitlySetAgentOptionsNames(): iterable
    {
        foreach ($this->agentOptions as $optNameToVal) {
            yield from array_keys($optNameToVal);
        }
    }

    /**
     * @return array<string, string|int|float|bool>
     */
    public function getAgentOptions(AgentConfigSourceKind $sourceKind): array
    {
        return ArrayUtil::getValueIfKeyExistsElse($sourceKind->asString(), $this->agentOptions, []);
    }

    /**
     * @return array<string, string|int|float|bool>
     */
    private function getExplicitlySetAgentOptions(): array
    {
        $iniOptions = $this->getAgentOptions(AgentConfigSourceKind::iniFile());
        $envVarsOptions = $this->getAgentOptions(AgentConfigSourceKind::envVars());

        /**
         * If the input arrays have the same string keys,
         * then the later value for that key will overwrite the previous one.
         *
         * .ini file source has higher precedence than environment variables.
         *
         * @link https://www.php.net/manual/en/function.array-merge.php
         */
        return array_merge($envVarsOptions, $iniOptions);
    }

    /**
     * @param string $optName
     *
     * @return mixed
     */
    private function getExplicitlySetAgentOptionValue(string $optName)
    {
        $explicitlySetOptions = $this->getExplicitlySetAgentOptions();
        return ArrayUtil::getValueIfKeyExistsElse($optName, $explicitlySetOptions, null);
    }

    public function getExplicitlySetAgentStringOptionValue(string $optName): ?string
    {
        $optVal = $this->getExplicitlySetAgentOptionValue($optName);
        if ($optVal !== null) {
            Assert::assertIsString($optVal);
        }
        return $optVal;
    }

    public function getEffectiveAgentConfig(): AgentConfigSnapshot
    {
        $envVarsToInheritSource = new MockConfigRawSnapshotSource();
        $envVars = $this->selectEnvVarsToInherit(EnvVarUtilForTests::getAll());
        foreach (IterableUtilForTests::keys(AllOptionsMetadata::get()) as $optName) {
            $envVarName = ConfigUtilForTests::agentOptionNameToEnvVarName($optName);
            if (array_key_exists($envVarName, $envVars)) {
                $envVarsToInheritSource->set($optName, $envVars[$envVarName]);
            }
        }

        $explicitlySetOptionsSource = new MockConfigRawSnapshotSource();
        foreach ($this->getExplicitlySetAgentOptions() as $optName => $optVal) {
            $explicitlySetOptionsSource->set($optName, strval($optVal));
        }
        $rawSnapshotSource = new CompositeRawSnapshotSource([$explicitlySetOptionsSource, $envVarsToInheritSource]);
        $allOptsMeta = AllOptionsMetadata::get();
        $rawSnapshot = $rawSnapshotSource->currentSnapshot($allOptsMeta);

        // Set log level above ERROR to hide potential errors when parsing the provided test configuration snapshot
        $logBackend = AmbientContextForTests::loggerFactory()->getBackend()->clone();
        $logBackend->setMaxEnabledLevel(LogLevel::CRITICAL);
        $loggerFactory = new LoggerFactory($logBackend);
        $parser = new ConfigParser($loggerFactory);
        return new AgentConfigSnapshot($parser->parse($allOptsMeta, $rawSnapshot), $loggerFactory);
    }
}
