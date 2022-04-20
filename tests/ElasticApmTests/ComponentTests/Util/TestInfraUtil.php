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

use Elastic\Apm\Impl\Util\IdGenerator;
use Elastic\Apm\Impl\Util\StaticClassTrait;
use RuntimeException;

final class TestInfraUtil
{
    use StaticClassTrait;

    public static function generateIdBasedOnTestCaseId(): string
    {
        return ComponentTestsPhpUnitExtension::$currentTestCaseId
               . '_' . IdGenerator::generateId(/* idLengthInBytes */ 16);
    }

    public static function buildTestInfraDataPerProcess(
        ?string $targetProcessServerId,
        ?int $targetProcessPort,
        ?ResourcesCleanerHandle $resourcesCleaner,
        ?string $agentEphemeralId
    ): TestInfraDataPerProcess {
        $result = new TestInfraDataPerProcess();

        $currentProcessId = getmypid();
        if ($currentProcessId === false) {
            throw new RuntimeException('Failed to get current process ID');
        }
        $result->rootProcessId = $currentProcessId;

        if ($resourcesCleaner !== null) {
            $result->resourcesCleanerServerId = $resourcesCleaner->getServerId();
            $result->resourcesCleanerPort = $resourcesCleaner->getPort();
        }

        $result->thisServerId = $targetProcessServerId;
        $result->thisServerPort = $targetProcessPort;

        $result->agentEphemeralId = $agentEphemeralId;

        return $result;
    }

    /**
     * @param array<string, string>   $baseEnvVars
     * @param ?string                 $targetProcessServerId
     * @param ?int                    $targetProcessPort
     * @param ?ResourcesCleanerHandle $resourcesCleaner
     * @param ?string                 $agentEphemeralId
     *
     * @return array<string, string>
     */
    public static function addTestInfraDataPerProcessToEnvVars(
        array $baseEnvVars,
        ?string $targetProcessServerId,
        ?int $targetProcessPort,
        ?ResourcesCleanerHandle $resourcesCleaner,
        ?string $agentEphemeralId
    ): array {
        $dataPerProcessEnvVarName = TestConfigUtil::envVarNameForTestOption(
            AllComponentTestsOptionsMetadata::DATA_PER_PROCESS_OPTION_NAME
        );
        $dataPerProcess = self::buildTestInfraDataPerProcess(
            $targetProcessServerId,
            $targetProcessPort,
            $resourcesCleaner,
            $agentEphemeralId
        );
        return $baseEnvVars + [$dataPerProcessEnvVarName => $dataPerProcess->serializeToString()];
    }

    public static function buildAppCodePhpCmd(?string $appCodePhpIni): string
    {
        $result = AmbientContext::testConfig()->appCodePhpExe ?? 'php';
        if ($appCodePhpIni !== null) {
            $result .= ' -c ' . $appCodePhpIni;
        }
        return $result;
    }
}
