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

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace ElasticApmTests\ComponentTests\Util;

use Elastic\Apm\Impl\Log\LoggableToString;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Util\ArrayUtil;
use Elastic\Apm\Impl\Util\ExceptionUtil;
use ElasticApmTests\Util\LogCategoryForTests;
use ErrorException;
use Exception;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Http\HttpServer;
use React\Promise\Promise;
use React\Socket\ConnectionInterface;
use React\Socket\SocketServer;
use RuntimeException;
use Throwable;

abstract class TestInfraHttpServerProcessBase extends SpawnedProcessBase
{
    use HttpServerProcessTrait;

    public const CLEAN_TEST_SCOPED_URI_PATH = '/clean_test_scoped';
    public const EXIT_URI_PATH = '/exit';

    /** @var Logger */
    private $logger;

    /** @var ?LoopInterface */
    protected $reactLoop = null;

    /** @var SocketServer[] */
    protected $serverSockets = [];

    public function __construct()
    {
        parent::__construct();

        $this->logger = AmbientContextForTests::loggerFactory()->loggerForClass(
            LogCategoryForTests::TEST_UTIL,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        )->addContext('this', $this);

        set_error_handler(
            function (
                int $type,
                string $message,
                string $srcFile,
                int $srcLine
            ): bool {
                $msgForEx = LoggableToString::convert(
                    [
                        'message' => $message,
                        'error type' => $type,
                        'srcFile:srcLine' => $srcFile . ':' . $srcLine,
                    ]
                );
                throw new ErrorException($msgForEx, /* code: */ 0, $type, $srcFile, $srcLine);
            }
        );
    }

    /** @inheritDoc */
    protected function processConfig(): void
    {
        parent::processConfig();

        Assert::assertCount(
            $this->expectedPortsCount(),
            AmbientContextForTests::testConfig()->dataPerProcess->thisServerPorts,
            LoggableToString::convert(AmbientContextForTests::testConfig())
        );

        // At this point request is not parsed and applied to config yet
        TestCase::assertNull(AmbientContextForTests::testConfig()->dataPerRequest);
    }

    /**
     * @return int
     */
    protected function expectedPortsCount(): int
    {
        return 1;
    }

    /**
     * @param int                 $socketIndex
     * @param ConnectionInterface $connection
     *
     * @return void
     */
    protected function onNewConnection(int $socketIndex, ConnectionInterface $connection): void
    {
        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'New connection',
            [
                'socketIndex' => $socketIndex,
                'connection addresses' => [
                    'remote' => $connection->getRemoteAddress(),
                    'local'  => $connection->getLocalAddress(),
                ]
            ]
        );
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return null|ResponseInterface|Promise
     */
    abstract protected function processRequest(ServerRequestInterface $request);

    public static function run(): void
    {
        self::runSkeleton(
            function (SpawnedProcessBase $thisObj): void {
                /** @var self $thisObj */
                $thisObj->runImpl();
            }
        );
    }

    public function runImpl(): void
    {
        try {
            $this->runHttpServer();
        } catch (Exception $ex) {
            ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->logThrowable($ex, 'Failed to start HTTP server - exiting...');
        }
    }

    private function runHttpServer(): void
    {
        $this->reactLoop = Loop::get();
        TestCase::assertNotEmpty(AmbientContextForTests::testConfig()->dataPerProcess->thisServerPorts);
        foreach (AmbientContextForTests::testConfig()->dataPerProcess->thisServerPorts as $port) {
            $uri = HttpServerHandle::DEFAULT_HOST . ':' . $port;
            $serverSocket = new SocketServer($uri, /* context */ [], $this->reactLoop);
            $socketIndex = count($this->serverSockets);
            $this->serverSockets[] = $serverSocket;
            $serverSocket->on(
                'connection' /* <- event */,
                function (ConnectionInterface $connection) use ($socketIndex): void {
                    $this->onNewConnection($socketIndex, $connection);
                }
            );
            $httpServer = new HttpServer(
                /**
                 * @param ServerRequestInterface $request
                 *
                 * @return ResponseInterface|Promise
                 */
                function (ServerRequestInterface $request) {
                    return $this->processRequestWrapper($request);
                }
            );
            ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Listening for incoming requests...',
                ['serverSocket address' => $serverSocket->getAddress()]
            );
            $httpServer->listen($serverSocket);
        }

        $this->beforeLoopRun();

        Assert::assertNotNull($this->reactLoop);
        $this->reactLoop->run();
    }

    /**
     * @return void
     */
    protected function beforeLoopRun(): void
    {
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return bool
     */
    protected function shouldRequestHaveSpawnedProcessInternalId(ServerRequestInterface $request): bool
    {
        return true;
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface|Promise
     */
    private function processRequestWrapper(ServerRequestInterface $request)
    {
        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Received request',
            ['URI' => $request->getUri(), 'method' => $request->getMethod(), 'target' => $request->getRequestTarget()]
        );

        try {
            $response = $this->processRequestWrapperImpl($request);

            if ($response instanceof ResponseInterface) {
                ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
                && $loggerProxy->log(
                    'Sending response ...',
                    [
                        'statusCode'   => $response->getStatusCode(),
                        'reasonPhrase' => $response->getReasonPhrase(),
                        'body'         => $response->getBody(),
                    ]
                );
            } else {
                TestCase::assertInstanceOf(Promise::class, $response);

                ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
                && $loggerProxy->log('Promise returned - response will be returned later...');
            }

            return $response;
        } catch (Throwable $throwable) {
            ($loggerProxy = $this->logger->ifCriticalLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'processRequest() exited by exception - terminating this process',
                ['$throwable' => $throwable]
            );
            exit(self::FAILURE_PROCESS_EXIT_CODE);
        }
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface|Promise
     */
    private function processRequestWrapperImpl(ServerRequestInterface $request)
    {
        if ($this->shouldRequestHaveSpawnedProcessInternalId($request)) {
            $testConfigForRequest = ConfigUtilForTests::read(
                new RequestHeadersRawSnapshotSource(
                    function (string $headerName) use ($request): ?string {
                        return self::getRequestHeader($request, $headerName);
                    }
                ),
                AmbientContextForTests::loggerFactory()
            );
            TestCase::assertNotNull($testConfigForRequest->dataPerRequest);
            $verifySpawnedProcessInternalIdResponse = self::verifySpawnedProcessInternalId(
                $testConfigForRequest->dataPerRequest->spawnedProcessInternalId
            );
            if ($verifySpawnedProcessInternalIdResponse !== null) {
                return $verifySpawnedProcessInternalIdResponse;
            }
        }

        if ($request->getUri()->getPath() === HttpServerHandle::STATUS_CHECK_URI_PATH) {
            return self::buildResponseWithPid();
        } elseif ($request->getUri()->getPath() === self::EXIT_URI_PATH) {
            $this->exit();
            return self::buildDefaultResponse();
        }

        if (($response = $this->processRequest($request)) !== null) {
            return $response;
        }

        return self::buildErrorResponse(
            HttpConstantsForTests::STATUS_BAD_REQUEST,
            'Unknown URI path: `' . $request->getRequestTarget() . '\''
        );
    }

    /**
     * @return void
     */
    protected function exit(): void
    {
        foreach ($this->serverSockets as $serverSocket) {
            $serverSocket->close();
        }

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Exiting...');
    }

    protected static function getRequestHeader(ServerRequestInterface $request, string $headerName): ?string
    {
        $headerValues = $request->getHeader($headerName);
        if (ArrayUtil::isEmpty($headerValues)) {
            return null;
        }
        if (count($headerValues) !== 1) {
            throw new RuntimeException(
                ExceptionUtil::buildMessage(
                    'The header should not have more than one value',
                    ['headerName' => $headerName, 'headerValues' => $headerValues]
                )
            );
        }
        return $headerValues[0];
    }

    protected static function getRequiredRequestHeader(ServerRequestInterface $request, string $headerName): string
    {
        $headerValue = self::getRequestHeader($request, $headerName);
        if ($headerValue === null) {
            throw new RuntimeException('Missing required HTTP request header `' . $headerName . '\'');
        }
        return $headerValue;
    }
}
