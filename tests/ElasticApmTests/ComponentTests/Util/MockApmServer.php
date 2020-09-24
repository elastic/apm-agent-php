<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests\Util;

use Ds\Map;
use Elastic\Apm\Impl\Clock;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Util\ObjectToStringBuilder;
use Elastic\Apm\Impl\Util\TextUtil;
use Elastic\Apm\Tests\Util\RangeUtil;
use Elastic\Apm\Tests\Util\TestLogCategory;
use Elastic\Apm\Tests\Util\TestTextUtil;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\LoopInterface;
use React\Http\Response;
use React\Promise\Promise;

final class MockApmServer extends StatefulHttpServerProcessBase
{
    public const MOCK_API_URI_PREFIX = '/mock_apm_server_api/';
    private const INTAKE_API_URI = '/intake/v2/events';
    public const GET_INTAKE_API_REQUESTS = 'get_intake_api_requests';
    public const FROM_INDEX_HEADER_NAME = TestEnvBase::HEADER_NAME_PREFIX . 'FROM_INDEX';
    public const INTAKE_API_REQUESTS_JSON_KEY = 'intake_api_requests_received_from_agent';

    /** @var int */
    public static $pendingDataRequestNextId = 1;

    /** @var Logger */
    private $logger;

    /** @var LoopInterface */
    private $reactLoop;

    /** @var IntakeApiRequest[] */
    private $receivedIntakeApiRequests = [];

    /** @var Map<int, MockApmServerPendingDataRequest> */
    private $pendingDataRequests;

    public function __construct(string $runScriptFile)
    {
        parent::__construct($runScriptFile);

        $this->pendingDataRequests = new Map();

        $this->logger = AmbientContext::loggerFactory()->loggerForClass(
            TestLogCategory::TEST_UTIL,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        )->addContext('this', $this);
    }

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

    protected function shouldRequestHaveServerId(ServerRequestInterface $request): bool
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
        $newRequest->timeReceivedAtServer = Clock::singletonInstance()->getSystemClockCurrentTime();
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
        $command = TestTextUtil::suffixFrom($request->getUri()->getPath(), strlen(self::MOCK_API_URI_PREFIX));

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
        $fromIndex = intval(self::getRequestHeader($request, self::FROM_INDEX_HEADER_NAME));
        if (!RangeUtil::isInInclusiveRange(0, $fromIndex, count($this->receivedIntakeApiRequests))) {
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
                    TestEnvBase::DATA_FROM_AGENT_MAX_WAIT_TIME_SECONDS,
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

        ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Sending response ...', ['fromIndex' => $fromIndex, 'newDataCount' => count($newData)]);

        return new Response(
            HttpConsts::STATUS_OK,
            // headers:
            ['Content-Type' => 'application/json'],
            // body:
            json_encode([self::INTAKE_API_REQUESTS_JSON_KEY => $newData], JSON_PRETTY_PRINT)
        );
    }

    private function fulfillTimedOutPendingDataRequest(int $pendingDataRequestId): void
    {
        $pendingDataRequest = $this->pendingDataRequests->remove($pendingDataRequestId, /* default */ null);
        if (is_null($pendingDataRequest)) {
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
            json_encode(
                [
                    'accepted' => 0,
                    'errors'   => [
                        [
                            'message' => $message,
                        ],
                    ],
                ],
                JSON_PRETTY_PRINT
            )
        );
    }

    protected function toStringAddProperties(ObjectToStringBuilder $builder): void
    {
        parent::toStringAddProperties($builder);
        $builder->add('pendingDataRequestsCount', $this->pendingDataRequests->count());
    }
}
