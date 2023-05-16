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
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Util\ArrayUtil;
use Elastic\Apm\Impl\Util\ClassNameUtil;
use Elastic\Apm\Impl\Util\JsonUtil;
use ElasticApmTests\Util\LogCategoryForTests;
use PHPUnit\Framework\Assert;
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
            ClassNameUtil::fqToShort(MockApmServer::class) /* <- dbgServerDesc */,
            $httpSpawnedProcessHandle->getSpawnedProcessOsId(),
            $httpSpawnedProcessHandle->getSpawnedProcessInternalId(),
            $httpSpawnedProcessHandle->getPorts()
        );

        $this->logger = AmbientContextForTests::loggerFactory()->loggerForClass(
            LogCategoryForTests::TEST_UTIL,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        )->addContext('this', $this);
    }

    public function getPortForAgent(): int
    {
        Assert::assertCount(2, $this->getPorts());
        return $this->getPorts()[1];
    }

    /**
     * @return RawDataFromAgentReceiverEvent[]
     */
    public function fetchNewData(): array
    {
        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Starting...');

        $response = $this->sendRequest(
            HttpConstantsForTests::METHOD_GET,
            MockApmServer::MOCK_API_URI_PREFIX . MockApmServer::GET_INTAKE_API_REQUESTS,
            [MockApmServer::FROM_INDEX_HEADER_NAME => strval($this->nextIntakeApiRequestIndexToFetch)]
        );

        $responseBody = $response->getBody()->getContents();
        if ($response->getStatusCode() !== HttpConstantsForTests::STATUS_OK) {
            throw new RuntimeException(
                'Received unexpected status code; ' . LoggableToString::convert(
                    [
                        'expected' => HttpConstantsForTests::STATUS_OK,
                        'actual'   => $response->getStatusCode(),
                        'body'     => $responseBody,
                    ]
                )
            );
        }

        /** @var array<string, mixed> $decodedBody */
        $decodedBody = JsonUtil::decode($responseBody, /* asAssocArray */ true);

        $receiverEventsJson = $decodedBody[MockApmServer::RAW_DATA_FROM_AGENT_RECEIVER_EVENTS_JSON_KEY];
        /** @var array<array<string, mixed>> $receiverEventsJson */
        $newReceiverEvents = [];
        foreach ($receiverEventsJson as $receiverEventJson) {
            $newReceiverEvent = RawDataFromAgentReceiverEvent::deserializeFromDecodedJson($receiverEventJson);
            $newReceiverEvents[] = $newReceiverEvent;
        }

        if (ArrayUtil::isEmpty($newReceiverEvents)) {
            ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log('Fetched NO new data from agent receiver events');
        } else {
            $this->nextIntakeApiRequestIndexToFetch += count($newReceiverEvents);
            ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Fetched new data from agent receiver events',
                ['count(newReceiverEvents)' => count($newReceiverEvents)]
            );
        }
        return $newReceiverEvents;
    }

    public function cleanTestScoped(): void
    {
        $this->nextIntakeApiRequestIndexToFetch = 0;

        $response = $this->sendRequest(
            HttpConstantsForTests::METHOD_POST,
            TestInfraHttpServerProcessBase::CLEAN_TEST_SCOPED_URI_PATH
        );
        Assert::assertSame(HttpConstantsForTests::STATUS_OK, $response->getStatusCode());
    }
}
