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

use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableTrait;
use Elastic\Apm\Impl\Util\ArrayUtil;
use PHPUnit\Framework\TestCase;

class AppCodeHostParams implements LoggableInterface
{
    use LoggableTrait;

    /** @var AgentConfigSourceKind */
    private $defaultAgentConfigSource;

    /** @var array<string, array<string, string|int|float>> */
    private $agentOptions = [];

    /** @var string */
    public $agentEphemeralId;

    public function __construct()
    {
        $this->defaultAgentConfigSource = AgentConfigSourceKind::envVars();
        $this->agentEphemeralId = TestInfraUtil::generateIdBasedOnTestCaseId();
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
}
