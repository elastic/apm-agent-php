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

use Elastic\Apm\Impl\Util\ClassNameUtil;
use PHPUnit\Framework\Assert;

final class GlobalTestInfra
{
    /** @var ResourcesCleanerHandle */
    protected $resourcesCleaner;

    /** @var MockApmServerHandle */
    protected $mockApmServer;

    /** @var int[] */
    private $portsInUse = [];

    public function __construct()
    {
        $this->resourcesCleaner = $this->startResourcesCleaner();
        $this->mockApmServer = $this->startMockApmServer($this->resourcesCleaner);
    }

    public function onTestStart(): void
    {
        $this->cleanTestScoped();
    }

    public function onTestEnd(): void
    {
        $this->cleanTestScoped();
    }

    private function cleanTestScoped(): void
    {
        $this->mockApmServer->cleanTestScoped();
        $this->resourcesCleaner->cleanTestScoped();
    }

    public function getResourcesCleaner(): ResourcesCleanerHandle
    {
        return $this->resourcesCleaner;
    }

    public function getMockApmServer(): MockApmServerHandle
    {
        return $this->mockApmServer;
    }

    /**
     * @return int[]
     */
    public function getPortsInUse(): array
    {
        return $this->portsInUse;
    }

    /**
     * @param int[] $ports
     *
     * @return void
     */
    private function addPortsInUse(array $ports): void
    {
        foreach ($ports as $port) {
            Assert::assertNotContains($port, $this->portsInUse);
            $this->portsInUse[] = $port;
        }
    }

    private function startResourcesCleaner(): ResourcesCleanerHandle
    {
        $httpServerHandle = TestInfraHttpServerStarter::startTestInfraHttpServer(
            ClassNameUtil::fqToShort(ResourcesCleaner::class) /* <- dbgProcessName */,
            'runResourcesCleaner.php' /* <- runScriptName */,
            $this->portsInUse,
            1 /* <- portsToAllocateCount */,
            null /* <- resourcesCleaner */
        );
        $this->addPortsInUse($httpServerHandle->getPorts());
        return new ResourcesCleanerHandle($httpServerHandle);
    }

    private function startMockApmServer(ResourcesCleanerHandle $resourcesCleaner): MockApmServerHandle
    {
        $httpServerHandle = TestInfraHttpServerStarter::startTestInfraHttpServer(
            ClassNameUtil::fqToShort(MockApmServer::class) /* <- dbgProcessName */,
            'runMockApmServer.php' /* <- runScriptName */,
            $this->portsInUse,
            2 /* <- portsToAllocateCount */,
            $resourcesCleaner
        );
        $this->addPortsInUse($httpServerHandle->getPorts());
        return new MockApmServerHandle($httpServerHandle);
    }
}
