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

use Ds\Map;
use Elastic\Apm\Impl\Clock;
use Elastic\Apm\Impl\Util\JsonUtil;
use Elastic\Apm\Impl\Util\NumericUtil;
use Elastic\Apm\Impl\Util\TextUtil;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\LoopInterface;
use React\Http\Message\Response;
use React\Promise\Promise;

final class MockApmServer extends TestInfraHttpServerProcessBase
{
    public const MOCK_API_URI_PREFIX = '/mock_apm_server_api/';
    private const INTAKE_API_URI = '/intake/v2/events';
    public const GET_INTAKE_API_REQUESTS = 'get_intake_api_requests';
    public const FROM_INDEX_HEADER_NAME = RequestHeadersRawSnapshotSource::HEADER_NAMES_PREFIX . 'FROM_INDEX';
    public const INTAKE_API_REQUESTS_JSON_KEY = 'intake_api_requests_received_from_agent';
    public const DATA_FROM_AGENT_MAX_WAIT_TIME_SECONDS = 10;

    /** @var int */
    public static $pendingDataRequestNextId = 1;

    /** @var LoopInterface */
    private $reactLoop;

    /** @var IntakeApiRequest[] */
    private $receivedIntakeApiRequests = [];

    /** @var Map<int, MockApmServerPendingDataRequest> */
    private $pendingDataRequests;

    public function __construct()
    {
        parent::__construct();

        $this->pendingDataRequests = new Map();
    }

    /** @inheritDoc */
    protected function beforeLoopRun(LoopInterface $loop): void
    {
        $this->reactLoop = $loop;
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface|Promise
     */
    protected function processRequest(ServerRequestInterface $request)
    {
        if ($request->getUri()->getPath() === self::INTAKE_API_URI) {
            return $this->processIntakeApiRequest($request);
        }

        if (TextUtil::isPrefixOf(self::MOCK_API_URI_PREFIX, $request->getUri()->getPath())) {
            return $this->processMockApiRequest($request);
        }

        return $this->buildErrorResponse(/* status */ 400, 'Unknown API path: `' . $request->getRequestTarget() . '\'');
    }

    protected function shouldRequestHaveSpawnedProcessId(ServerRequestInterface $request): bool
    {
        return $request->getUri()->getPath() !== self::INTAKE_API_URI;
    }

    private function processIntakeApiRequest(ServerRequestInterface $request): ResponseInterface
    {
        if ($request->getBody()->getSize() === 0) {
            return $this->buildIntakeApiErrorResponse(
                400 /* status */,
                'Intake API request should not have empty body'
            );
        }

        $newRequest = new IntakeApiRequest();
        $newRequest->timeReceivedAtApmServer = Clock::singletonInstance()->getSystemClockCurrentTime();
        $newRequest->headers = $request->getHeaders();
        $newRequest->body = $request->getBody()->getContents();
        $this->receivedIntakeApiRequests[] = $newRequest;

        foreach ($this->pendingDataRequests as $pendingDataRequest) {
            $this->reactLoop->cancelTimer($pendingDataRequest->timer);
            ($pendingDataRequest->resolveCallback)($this->fulfillDataRequest($pendingDataRequest->fromIndex));
        }
        $this->pendingDataRequests->clear();

        return new Response(/* status: */ 202);
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface|Promise
     */
    private function processMockApiRequest(ServerRequestInterface $request)
    {
        $command = substr($request->getUri()->getPath(), strlen(self::MOCK_API_URI_PREFIX));

        if ($command === self::GET_INTAKE_API_REQUESTS) {
            return $this->getIntakeApiRequests($request);
        }

        return $this->buildErrorResponse(400 /* status */, 'Unknown Mock API command `' . $command . '\'');
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface|Promise
     */
    private function getIntakeApiRequests(ServerRequestInterface $request)
    {
        $fromIndex = intval(self::getRequiredRequestHeader($request, self::FROM_INDEX_HEADER_NAME));
        if (!NumericUtil::isInClosedInterval(0, $fromIndex, count($this->receivedIntakeApiRequests))) {
            return $this->buildErrorResponse(
                400 /* status */,
                'Invalid `' . self::FROM_INDEX_HEADER_NAME . '\' HTTP request header value: $fromIndex'
                . ' (should be in range[0, ' . count($this->receivedIntakeApiRequests) . '])'
            );
        }

        if ($this->hasNewDataFromAgentRequest($fromIndex)) {
            return $this->fulfillDataRequest($fromIndex);
        }

        return new Promise(
            function ($resolve) use ($fromIndex) {
                $pendingDataRequestId = self::$pendingDataRequestNextId++;
                $timer = $this->reactLoop->addTimer(
                    self::DATA_FROM_AGENT_MAX_WAIT_TIME_SECONDS,
                    function () use ($pendingDataRequestId) {
                        $this->fulfillTimedOutPendingDataRequest($pendingDataRequestId);
                    }
                );
                $this->pendingDataRequests->put(
                    $pendingDataRequestId,
                    new MockApmServerPendingDataRequest($fromIndex, $resolve, $timer)
                );
            }
        );
    }

    private function hasNewDataFromAgentRequest(int $fromIndex): bool
    {
        return count($this->receivedIntakeApiRequests) > $fromIndex;
    }

    private function fulfillDataRequest(int $fromIndex): ResponseInterface
    {
        $newData = $this->hasNewDataFromAgentRequest($fromIndex)
            ? array_slice($this->receivedIntakeApiRequests, $fromIndex)
            : [];

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Sending response ...', ['fromIndex' => $fromIndex, 'newDataCount' => count($newData)]);

        return new Response(
            HttpConsts::STATUS_OK,
            // headers:
            ['Content-Type' => 'application/json'],
            // body:
            JsonUtil::encode([self::INTAKE_API_REQUESTS_JSON_KEY => $newData], /* prettyPrint: */ true)
        );
    }

    private function fulfillTimedOutPendingDataRequest(int $pendingDataRequestId): void
    {
        $pendingDataRequest = $this->pendingDataRequests->remove($pendingDataRequestId, /* default: */ null);
        if ($pendingDataRequest === null) {
            // If request is already fulfilled then just return
            return;
        }

        ($loggerProxy = $this->logger->ifWarningLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Timed out while waiting for ' . self::GET_INTAKE_API_REQUESTS . ' to be fulfilled'
            . ' - returning empty data set...',
            ['pendingDataRequestId' => $pendingDataRequestId]
        );

        ($pendingDataRequest->resolveCallback)($this->fulfillDataRequest($pendingDataRequest->fromIndex));
    }

    protected function buildIntakeApiErrorResponse(int $status, string $message): ResponseInterface
    {
        return new Response(
            $status,
            // headers:
            [
                'Content-Type' => 'application/json',
            ],
            // body:
            JsonUtil::encode(
                [
                    'accepted' => 0,
                    'errors'   => [
                        [
                            'message' => $message,
                        ],
                    ],
                ],
                /* prettyPrint: */ true
            )
        );
    }
}
