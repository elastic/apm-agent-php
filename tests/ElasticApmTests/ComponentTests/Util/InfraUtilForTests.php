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

final class InfraUtilForTests
{
    use StaticClassTrait;

    public static function generateSpawnedProcessInternalId(): string
    {
        return IdGenerator::generateId(/* idLengthInBytes */ 16);
    }

    /**
     * @param string                  $targetSpawnedProcessInternalId
     * @param int[]                   $targetServerPorts
     * @param ?ResourcesCleanerHandle $resourcesCleaner
     *
     * @return TestInfraDataPerProcess
     */
    public static function buildTestInfraDataPerProcess(
        string $targetSpawnedProcessInternalId,
        array $targetServerPorts,
        ?ResourcesCleanerHandle $resourcesCleaner
    ): TestInfraDataPerProcess {
        $result = new TestInfraDataPerProcess();

        $currentProcessId = getmypid();
        if ($currentProcessId === false) {
            throw new RuntimeException('Failed to get current process ID');
        }
        $result->rootProcessId = $currentProcessId;

        if ($resourcesCleaner !== null) {
            $result->resourcesCleanerSpawnedProcessInternalId = $resourcesCleaner->getSpawnedProcessInternalId();
            $result->resourcesCleanerPort = $resourcesCleaner->getMainPort();
        }

        $result->thisSpawnedProcessInternalId = $targetSpawnedProcessInternalId;
        $result->thisServerPorts = $targetServerPorts;

        return $result;
    }

    /**
     * @param array<string, string>   $baseEnvVars
     * @param string                  $targetSpawnedProcessInternalId
     * @param int[]                   $targetServerPorts
     * @param ?ResourcesCleanerHandle $resourcesCleaner
     * @param string                  $dbgProcessName
     *
     * @return array<string, string>
     */
    public static function addTestInfraDataPerProcessToEnvVars(
        array $baseEnvVars,
        string $targetSpawnedProcessInternalId,
        array $targetServerPorts,
        ?ResourcesCleanerHandle $resourcesCleaner,
        string $dbgProcessName
    ): array {
        $dataPerProcessOptName = AllComponentTestsOptionsMetadata::DATA_PER_PROCESS_OPTION_NAME;
        $dataPerProcessEnvVarName = ConfigUtilForTests::testOptionNameToEnvVarName($dataPerProcessOptName);
        $dataPerProcess = self::buildTestInfraDataPerProcess(
            $targetSpawnedProcessInternalId,
            $targetServerPorts,
            $resourcesCleaner
        );
        return $baseEnvVars
               + [
                   SpawnedProcessBase::DBG_PROCESS_NAME_ENV_VAR_NAME => $dbgProcessName,
                   $dataPerProcessEnvVarName => $dataPerProcess->serializeToString(),
               ];
    }

    public static function buildAppCodePhpCmd(?string $appCodePhpIni): string
    {
        $result = AmbientContextForTests::testConfig()->appCodePhpExe ?? 'php';
        if ($appCodePhpIni !== null) {
            $result .= ' -c ' . $appCodePhpIni;
        }
        return $result;
    }
}
