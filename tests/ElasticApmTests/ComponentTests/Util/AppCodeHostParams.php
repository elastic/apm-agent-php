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
use Elastic\Apm\Impl\Config\Parser as ConfigParser;
use Elastic\Apm\Impl\Config\Snapshot as AgentConfigSnapshot;
use Elastic\Apm\Impl\Config\Snapshot as ConfigSnapshot;
use Elastic\Apm\Impl\Log\Level as LogLevel;
use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableTrait;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Log\LoggerFactory;
use Elastic\Apm\Impl\Util\ArrayUtil;
use ElasticApmTests\UnitTests\Util\MockConfigRawSnapshotSource;
use ElasticApmTests\Util\LogCategoryForTests;
use ElasticApmTests\Util\RandomUtilForTests;
use PHPUnit\Framework\TestCase;

class AppCodeHostParams implements LoggableInterface
{
    use LoggableTrait;

    /** @var string */
    public $dbgProcessName;

    /** @var AgentConfigSourceKind */
    private $defaultAgentConfigSource;

    /** @var array<string, array<string, string|int|float>> */
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
     * @param string|int|float       $optVal
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
     * @return string[]
     */
    public function getSetAgentOptionNames(): array
    {
        $result = [];
        foreach ($this->agentOptions as $optNameToVal) {
            TestCase::assertIsArray($optNameToVal);
            $result = array_merge($result, array_keys($optNameToVal));
        }
        return $result;
    }

    /**
     * @return array<string, string|int|float>
     */
    public function getAgentOptions(AgentConfigSourceKind $sourceKind): array
    {
        return ArrayUtil::getValueIfKeyExistsElse($sourceKind->asString(), $this->agentOptions, []);
    }

    /**
     * @return array<string, string|int|float>
     */
    public function getEffectiveAgentOptions(): array
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

    public function getEffectiveAgentConfig(): AgentConfigSnapshot
    {
        $configRawSnapshotSource = new MockConfigRawSnapshotSource();
        foreach ($this->getEffectiveAgentOptions() as $optName => $optVal) {
            $configRawSnapshotSource->set($optName, strval($optVal));
        }
        // Set log level above ERROR to hide potential errors when parsing the provided test configuration snapshot
        $logBackend = AmbientContextForTests::loggerFactory()->getBackend()->clone();
        $logBackend->setMaxEnabledLevel(LogLevel::CRITICAL);
        $loggerFactory = new LoggerFactory($logBackend);
        $parser = new ConfigParser($loggerFactory);
        $allOptsMeta = AllOptionsMetadata::get();
        $rawSnapshot = $configRawSnapshotSource->currentSnapshot($allOptsMeta);
        return new ConfigSnapshot($parser->parse($allOptsMeta, $rawSnapshot), $loggerFactory);
    }

    public function getSetAgentOptionValue(string $optName): ?string
    {
        $setAgentOptions = $this->getEffectiveAgentOptions();
        $optVal = ArrayUtil::getValueIfKeyExistsElse($optName, $setAgentOptions, null);
        return $optVal === null ? null : strval($optVal);
    }
}
