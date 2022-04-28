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
use Elastic\Apm\Impl\Util\UrlParts;
use ElasticApmTests\Util\LogCategoryForTests;
use GuzzleHttp\Exception\GuzzleException;

final class ResourcesCleanerHandle extends HttpServerHandle
{
    public function __construct(HttpServerHandle $httpSpawnedProcessHandle)
    {
        parent::__construct($httpSpawnedProcessHandle->getSpawnedProcessId(), $httpSpawnedProcessHandle->getPort());
    }

    public function signalToExit(): void
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
                TestInfraDataPerRequest::withSpawnedProcessId($this->getSpawnedProcessId())
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
