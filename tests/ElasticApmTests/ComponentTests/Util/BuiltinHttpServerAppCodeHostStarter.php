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

use ElasticApmTests\Util\FileUtilForTests;
use PHPUnit\Framework\Assert;

final class BuiltinHttpServerAppCodeHostStarter extends HttpServerStarter
{
    private const APP_CODE_HOST_ROUTER_SCRIPT = 'routeToBuiltinHttpServerAppCodeHost.php';

    /** @var HttpAppCodeHostParams */
    private $appCodeHostParams;

    /** @var AgentConfigSourceBuilder */
    private $agentConfigSourceBuilder;

    /** @var ResourcesCleanerHandle */
    private $resourcesCleaner;

    private function __construct(
        HttpAppCodeHostParams $appCodeHostParams,
        AgentConfigSourceBuilder $agentConfigSourceBuilder,
        ResourcesCleanerHandle $resourcesCleaner
    ) {
        parent::__construct($appCodeHostParams->dbgProcessName);

        $this->appCodeHostParams = $appCodeHostParams;
        $this->agentConfigSourceBuilder = $agentConfigSourceBuilder;
        $this->resourcesCleaner = $resourcesCleaner;
    }

    /**
     * @param HttpAppCodeHostParams    $appCodeHostParams
     * @param AgentConfigSourceBuilder $agentConfigSourceBuilder
     * @param int[]                    $portsInUse
     * @param ResourcesCleanerHandle   $resourcesCleaner
     *
     * @return HttpServerHandle
     */
    public static function startBuiltinHttpServerAppCodeHost(
        HttpAppCodeHostParams $appCodeHostParams,
        AgentConfigSourceBuilder $agentConfigSourceBuilder,
        ResourcesCleanerHandle $resourcesCleaner,
        array $portsInUse
    ): HttpServerHandle {
        return (new self($appCodeHostParams, $agentConfigSourceBuilder, $resourcesCleaner))
            ->startHttpServer($portsInUse);
    }

    /** @inheritDoc */
    protected function buildCommandLine(array $ports): string
    {
        Assert::assertCount(1, $ports);
        return InfraUtilForTests::buildAppCodePhpCmd($this->agentConfigSourceBuilder->getPhpIniFile())
               . " -S localhost:" . $ports[0]
               . ' "' . FileUtilForTests::listToPath([__DIR__, self::APP_CODE_HOST_ROUTER_SCRIPT]) . '"';
    }

    /** @inheritDoc */
    protected function buildEnvVars(string $spawnedProcessInternalId, array $ports): array
    {
        Assert::assertCount(1, $ports);
        return InfraUtilForTests::addTestInfraDataPerProcessToEnvVars(
            $this->agentConfigSourceBuilder->getEnvVars(EnvVarUtilForTests::getAll()),
            $spawnedProcessInternalId,
            $ports,
            $this->resourcesCleaner,
            $this->appCodeHostParams->dbgProcessName
        );
    }
}
