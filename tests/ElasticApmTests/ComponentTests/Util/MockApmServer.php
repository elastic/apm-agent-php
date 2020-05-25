<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests\Util;

use Elastic\Apm\Impl\Clock;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Util\TextUtil;
use Elastic\Apm\Tests\Util\RangeUtil;
use Elastic\Apm\Tests\Util\TestLogCategory;
use Elastic\Apm\Tests\Util\TestTextUtil;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Response;
use RuntimeException;

final class MockApmServer extends StatefulHttpServerProcessBase
{
    private const MOCK_API_URI_PREFIX = '/mock_apm_server_api/';
    private const INTAKE_API_URI = '/intake/v2/events';
    private const GET_INTAKE_API_REQUESTS = 'get_intake_api_requests';
    private const FROM_INDEX_HEADER_NAME = TestEnvBase::HEADER_NAME_PREFIX . 'FROM_INDEX';
    public const INTAKE_API_REQUESTS_JSON_KEY = 'intake_api_requests_received_from_agent';

    /** @var Logger */
    private $logger;

    /** @var IntakeApiRequest[] */
    private $receivedRequests = [];

    public function __construct(string $runScriptFile)
    {
        parent::__construct($runScriptFile);

        $this->logger = AmbientContext::loggerFactory()->loggerForClass(
            TestLogCategory::TEST_UTIL,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        )->addContext('this', $this);
    }

    public static function sendRequestToGetAgentIntakeApiRequests(
        int $port,
        string $testEnvId,
        int $fromIndex
    ): ResponseInterface {
        /** @noinspection PhpUnhandledExceptionInspection */
        $response = self::sendRequest(
            $port,
            HttpConsts::METHOD_GET,
            self::MOCK_API_URI_PREFIX . self::GET_INTAKE_API_REQUESTS,
            [
                TestEnvBase::TEST_ENV_ID_HEADER_NAME => $testEnvId,
                self::FROM_INDEX_HEADER_NAME => strval($fromIndex)
            ]
        );

        if ($response->getStatusCode() !== HttpConsts::STATUS_OK) {
            throw new RuntimeException('Received unexpected status code');
        }

        return $response;
    }

    protected function processRequest(ServerRequestInterface $request): ResponseInterface
    {
        if ($request->getUri()->getPath() === self::INTAKE_API_URI) {
            return $this->processIntakeApiRequest($request);
        }

        if (TextUtil::isPrefixOf(self::MOCK_API_URI_PREFIX, $request->getUri()->getPath())) {
            return $this->processMockApiRequest($request);
        }

        return $this->buildErrorResponse(/* status */ 400, 'Unknown API path: `' . $request->getRequestTarget() . '\'');
    }

    protected function shouldHaveTestEnvId(ServerRequestInterface $request): bool
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
        $this->receivedRequests[] = $newRequest;
        return new Response(/* status: */ 202);
    }

    private function processMockApiRequest(ServerRequestInterface $request): ResponseInterface
    {
        $command = TestTextUtil::suffixFrom($request->getUri()->getPath(), strlen(self::MOCK_API_URI_PREFIX));

        if ($command === self::GET_INTAKE_API_REQUESTS) {
            return $this->getIntakeApiRequests($request);
        }

        return $this->buildErrorResponse(400 /* status */, 'Unknown Mock API command `' . $command . '\'');
    }

    private function getIntakeApiRequests(ServerRequestInterface $request): ResponseInterface
    {
        $fromIndex = intval(self::getRequestHeader($request, self::FROM_INDEX_HEADER_NAME));
        if (!RangeUtil::isInInclusiveRange(0, $fromIndex, count($this->receivedRequests))) {
            return $this->buildErrorResponse(
                400 /* status */,
                'Invalid `' . self::FROM_INDEX_HEADER_NAME . '\' HTTP request header value: $fromIndex'
                . ' (should be in range[0, ' . count($this->receivedRequests) . '])'
            );
        }

        return new Response(
            HttpConsts::STATUS_OK,
            // headers:
            ['Content-Type' => 'application/json'],
            // body:
            json_encode(
                [self::INTAKE_API_REQUESTS_JSON_KEY => array_slice($this->receivedRequests, $fromIndex)],
                JSON_PRETTY_PRINT
            )
        );
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
}
