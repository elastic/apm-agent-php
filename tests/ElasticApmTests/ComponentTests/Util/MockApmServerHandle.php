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

final class MockApmServerHandle extends HttpServerHandle
{
    // /** @var Logger */
    // private $logger;

    // /** @var int */
    // private $nextIntakeApiRequest = 0;

    /** @var DataFromAgent */
    private $dataFromAgent;

    public function __construct(HttpServerHandle $httpServerHandle)
    {
        parent::__construct($httpServerHandle->getPort(), $httpServerHandle->getServerId());

        // $this->logger = AmbientContext::loggerFactory()->loggerForClass(
        //     LogCategoryForTests::TEST_UTIL,
        //     __NAMESPACE__,
        //     __CLASS__,
        //     __FILE__
        // )->addContext('this', $this);

        $this->dataFromAgent = new DataFromAgent();
    }

    public function ensureLatestData(): void
    {
        // ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        // && $loggerProxy->log('Starting...');
        //
        // try {
        //     $newIntakeApiRequests = $this->fetchLatestData();
        //     if (!empty($newIntakeApiRequests)) {
        //         $this->dataFromAgent->addIntakeApiRequests($newIntakeApiRequests, $timeBeforeRequestToApp);
        //     }
        //
        //     ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        //     && $loggerProxy->log('Done');
        //     return;
        // } catch (Throwable $thrown) {
        //     ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
        //     && $loggerProxy->log(
        //         'Failed to process data from the agent',
        //         ['thrown' => $thrown]
        //     );
        //     /** @noinspection PhpUnhandledExceptionInspection */
        //     throw $thrown;
        // }
    }

    public function getAccumulatedData(): DataFromAgent
    {
        return $this->dataFromAgent;
    }

    // /**
    //  * @return IntakeApiRequest[]
    //  */
    // private function fetchLatestData(): void
    // {
    //     $response = TestHttpClientUtil::sendRequest(
    //         HttpConsts::METHOD_GET,
    //         (new UrlParts())
    //             ->path(MockApmServer::MOCK_API_URI_PREFIX . MockApmServer::GET_INTAKE_API_REQUESTS)
    //             ->port($this->mockApmServer->getPort()),
    //         TestInfraDataPerRequest::withServerId($this->mockApmServerId),
    //         [MockApmServer::FROM_INDEX_HEADER_NAME
    //              => strval($this->dataFromAgent->nextIntakeApiRequestIndexToFetch())]
    //     );
    //
    //     if ($response->getStatusCode() !== HttpConsts::STATUS_OK) {
    //         throw new RuntimeException('Received unexpected status code');
    //     }
    //
    //     $decodedBody = JsonUtil::decode($response->getBody()->getContents(), /* asAssocArray */ true);
    //     /** @var array<string, mixed> $decodedBody */
    //
    //     $requestsJson = $decodedBody[MockApmServer::INTAKE_API_REQUESTS_JSON_KEY];
    //     /** @var array<array<string, mixed>> $requestsJson */
    //     $newIntakeApiRequests = [];
    //     foreach ($requestsJson as $requestJson) {
    //         $newIntakeApiRequests[] = IntakeApiRequest::jsonDeserialize($requestJson);
    //     }
    //
    //     if (!empty($newIntakeApiRequests)) {
    //         ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
    //         && $loggerProxy->log(
    //             'Fetched new intake API requests received from agent',
    //             ['newIntakeApiRequestsCount' => count($newIntakeApiRequests)]
    //         );
    //     }
    //     return $newIntakeApiRequests;
    // }
}
