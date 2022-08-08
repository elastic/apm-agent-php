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

final class TestInfraHttpServerStarter extends HttpServerStarter
{
    /** @var string */
    private $runScriptName;

    /** @var ?ResourcesCleanerHandle */
    private $resourcesCleaner;

    /**
     * @param string                  $dbgProcessName
     * @param string                  $runScriptName
     * @param int[]                   $portsInUse
     * @param ?ResourcesCleanerHandle $resourcesCleaner
     *
     * @return HttpServerHandle
     */
    public static function startTestInfraHttpServer(
        string $dbgProcessName,
        string $runScriptName,
        array $portsInUse,
        ?ResourcesCleanerHandle $resourcesCleaner
    ): HttpServerHandle {
        return (new self($dbgProcessName, $runScriptName, $resourcesCleaner))->startHttpServer($portsInUse);
    }

    /**
     * @param string                  $dbgProcessName
     * @param string                  $runScriptName
     * @param ?ResourcesCleanerHandle $resourcesCleaner
     */
    private function __construct(
        string $dbgProcessName,
        string $runScriptName,
        ?ResourcesCleanerHandle $resourcesCleaner
    ) {
        parent::__construct($dbgProcessName);

        $this->runScriptName = $runScriptName;
        $this->resourcesCleaner = $resourcesCleaner;
    }

    /** @inheritDoc */
    protected function buildCommandLine(int $port): string
    {
        return 'php ' . '"' . __DIR__ . DIRECTORY_SEPARATOR . $this->runScriptName . '"';
    }

    /** @inheritDoc */
    protected function buildEnvVars(string $spawnedProcessId, int $port): array
    {
        return TestInfraUtil::addTestInfraDataPerProcessToEnvVars(
            getenv(),
            $spawnedProcessId,
            $port,
            $this->resourcesCleaner,
            $this->dbgProcessName
        );
    }
}
