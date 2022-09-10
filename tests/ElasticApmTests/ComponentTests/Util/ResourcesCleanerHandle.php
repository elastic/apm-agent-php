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

use Elastic\Apm\Impl\Log\LoggableToString;
use Elastic\Apm\Impl\Util\ClassNameUtil;
use Elastic\Apm\Impl\Util\UrlParts;
use ElasticApmTests\Util\LogCategoryForTests;
use GuzzleHttp\Exception\GuzzleException;
use PHPUnit\Framework\TestCase;

final class ResourcesCleanerHandle extends HttpServerHandle
{
    private const MAX_WAIT_TO_EXIT_MICROSECONDS = 10 * 1000 * 1000; // 10 seconds

    /** @var ResourcesClient */
    private $resourcesClient;

    public function __construct(HttpServerHandle $httpSpawnedProcessHandle)
    {
        parent::__construct(
            $httpSpawnedProcessHandle->getSpawnedProcessOsId(),
            $httpSpawnedProcessHandle->getSpawnedProcessInternalId(),
            $httpSpawnedProcessHandle->getPort()
        );

        $this->resourcesClient = new ResourcesClient($this->getSpawnedProcessInternalId(), $this->getPort());
    }

    public function getClient(): ResourcesClient
    {
        return $this->resourcesClient;
    }

    public function signalAndWaitForItToExit(): void
    {
        $this->signalToExit();

        $hasExited = ProcessUtilForTests::waitForProcessToExit(
            ClassNameUtil::fqToShort(ResourcesCleaner::class) /* <- dbgProcessDesc */,
            $this->getSpawnedProcessOsId(),
            self::MAX_WAIT_TO_EXIT_MICROSECONDS
        );
        TestCase::assertTrue($hasExited, LoggableToString::convert(['$this' => $this]));
    }

    private function signalToExit(): void
    {
        $logger = AmbientContextForTests::loggerFactory()->loggerForClass(
            LogCategoryForTests::TEST_UTIL,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        )->addContext('this', $this);

        ($loggerProxy = $logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Signaling ' . ClassNameUtil::fqToShort(ResourcesCleaner::class) . ' to clean and exit...'
        );

        try {
            HttpClientUtilForTests::sendRequest(
                HttpConsts::METHOD_POST,
                (new UrlParts())->path(ResourcesCleaner::CLEAN_AND_EXIT_URI_PATH)->port($this->getPort()),
                TestInfraDataPerRequest::withSpawnedProcessInternalId($this->getSpawnedProcessInternalId())
            );
        } catch (GuzzleException $ex) {
            // clean-and-exit request is expected to throw
            // because ResourcesCleaner process exits before responding
        }

        ($loggerProxy = $logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Signaled ' . ClassNameUtil::fqToShort(ResourcesCleaner::class) . ' to clean and exit'
        );
    }
}
