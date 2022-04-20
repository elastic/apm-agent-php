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

    public static function startTestInfraHttpServer(
        string $dbgServerDesc,
        string $runScriptName,
        ?ResourcesCleanerHandle $resourcesCleaner
    ): HttpServerHandle {
        return (new self($dbgServerDesc, $runScriptName, $resourcesCleaner))->startHttpServer();
    }

    /**
     * @param string                  $dbgServerDesc
     * @param string                  $runScriptName
     * @param ?ResourcesCleanerHandle $resourcesCleaner
     */
    private function __construct(
        string $dbgServerDesc,
        string $runScriptName,
        ?ResourcesCleanerHandle $resourcesCleaner
    ) {
        parent::__construct($dbgServerDesc);

        $this->runScriptName = $runScriptName;
        $this->resourcesCleaner = $resourcesCleaner;
    }

    /** @inheritDoc */
    protected function buildCommandLine(int $port): string
    {
        return 'php ' . '"' . __DIR__ . DIRECTORY_SEPARATOR . $this->runScriptName . '"';
    }

    /** @inheritDoc */
    protected function buildEnvVars(int $port, string $serverId): array
    {
        return TestInfraUtil::addTestInfraDataPerProcessToEnvVars(
            getenv(),
            $serverId,
            $port,
            $this->resourcesCleaner,
            null /* <- agentEphemeralId */
        );
    }
}
