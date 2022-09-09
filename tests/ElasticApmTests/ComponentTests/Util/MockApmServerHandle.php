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

use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Util\ArrayUtil;
use Elastic\Apm\Impl\Util\JsonUtil;
use Elastic\Apm\Impl\Util\UrlParts;
use ElasticApmTests\Util\LogCategoryForTests;
use RuntimeException;

final class MockApmServerHandle extends HttpServerHandle
{
    /** @var Logger */
    private $logger;

    /** @var int */
    private $nextIntakeApiRequestIndexToFetch = 0;

    public function __construct(HttpServerHandle $httpSpawnedProcessHandle)
    {
        parent::__construct(
            $httpSpawnedProcessHandle->getSpawnedProcessOsId(),
            $httpSpawnedProcessHandle->getSpawnedProcessInternalId(),
            $httpSpawnedProcessHandle->getPort()
        );

        $this->logger = AmbientContextForTests::loggerFactory()->loggerForClass(
            LogCategoryForTests::TEST_UTIL,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        )->addContext('this', $this);
    }

    /**
     * @return IntakeApiRequest[]
     */
    public function fetchNewData(): array
    {
        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Starting...');

        $response = HttpClientUtilForTests::sendRequest(
            HttpConsts::METHOD_GET,
            (new UrlParts())
                ->path(MockApmServer::MOCK_API_URI_PREFIX . MockApmServer::GET_INTAKE_API_REQUESTS)
                ->port($this->getPort()),
            TestInfraDataPerRequest::withSpawnedProcessInternalId($this->getSpawnedProcessInternalId()),
            [MockApmServer::FROM_INDEX_HEADER_NAME => strval($this->nextIntakeApiRequestIndexToFetch)]
        );

        if ($response->getStatusCode() !== HttpConsts::STATUS_OK) {
            throw new RuntimeException('Received unexpected status code');
        }

        /** @var array<string, mixed> $decodedBody */
        $decodedBody = JsonUtil::decode($response->getBody()->getContents(), /* asAssocArray */ true);

        $requestsJson = $decodedBody[MockApmServer::INTAKE_API_REQUESTS_JSON_KEY];
        /** @var array<array<string, mixed>> $requestsJson */
        $newIntakeApiRequests = [];
        foreach ($requestsJson as $requestJson) {
            $newIntakeApiRequest = new IntakeApiRequest();
            $newIntakeApiRequest->deserializeFromDecodedJson($requestJson);
            $newIntakeApiRequests[] = $newIntakeApiRequest;
        }

        if (ArrayUtil::isEmpty($newIntakeApiRequests)) {
            ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log('Fetched NO new intake API requests received from agent');
        } else {
            $this->nextIntakeApiRequestIndexToFetch += count($newIntakeApiRequests);
            ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Fetched new intake API requests received from agent',
                ['count($newIntakeApiRequests)' => count($newIntakeApiRequests)]
            );
        }
        return $newIntakeApiRequests;
    }
}
