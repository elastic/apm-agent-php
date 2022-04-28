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
use Elastic\Apm\Impl\Util\ExceptionUtil;
use ErrorException;
use Exception;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Http\HttpServer;
use React\Promise\Promise;
use React\Socket\SocketServer;
use RuntimeException;
use Throwable;

abstract class TestInfraHttpServerProcessBase extends SpawnedProcessBase
{
    use HttpServerProcessTrait;

    public function __construct()
    {
        parent::__construct();

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

    protected function processConfig(): void
    {
        parent::processConfig();

        TestCase::assertNotNull(
            AmbientContextForTests::testConfig()->dataPerProcess->thisServerPort,
            LoggableToString::convert(AmbientContextForTests::testConfig())
        );

        // At this point request is not parsed and applied to config yet
        TestCase::assertNull(AmbientContextForTests::testConfig()->dataPerRequest);
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface|Promise
     */
    abstract protected function processRequest(ServerRequestInterface $request);

    public static function run(): void
    {
        self::runSkeleton(
            function (SpawnedProcessBase $thisObjArg): void {
                /** var StatefulHttpServerProcessBase */
                $thisObj = $thisObjArg;
                $thisObj->runImpl(); // @phpstan-ignore-line
            }
        );
    }

    public function runImpl(): void
    {
        try {
            $this->runHttpServer();
        } catch (Exception $ex) {
            ($loggerProxy = $this->logger->ifWarningLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->logThrowable($ex, 'Failed to start HTTP server - exiting...');
        }
    }

    private function runHttpServer(): void
    {
        $loop = Loop::get();

        assert(AmbientContextForTests::testConfig()->dataPerProcess->thisServerPort !== null);
        $serverSocket = new SocketServer(
            HttpServerHandle::DEFAULT_HOST . ':' . AmbientContextForTests::testConfig()->dataPerProcess->thisServerPort,
            [] /* <- context */,
            $loop
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

        $httpServer->listen($serverSocket);

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Waiting for incoming requests...',
            ['serverSocketAddress' => $serverSocket->getAddress()]
        );

        $this->beforeLoopRun($loop);

        $loop->run();
    }

    protected function beforeLoopRun(LoopInterface $loop): void
    {
    }

    protected function shouldRequestHaveSpawnedProcessId(ServerRequestInterface $request): bool
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
                    ['statusCode' => $response->getStatusCode(), 'reasonPhrase' => $response->getReasonPhrase()]
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
        if ($this->shouldRequestHaveSpawnedProcessId($request)) {
            $testConfigForRequest = TestConfigUtil::read(
                AmbientContextForTests::dbgProcessName(),
                new RequestHeadersRawSnapshotSource(
                    function (string $headerName) use ($request): ?string {
                        return self::getRequestHeader($request, $headerName);
                    }
                )
            );
            TestCase::assertNotNull($testConfigForRequest->dataPerRequest);
            $verifySpawnedProcessIdResponse
                = self::verifySpawnedProcessId($testConfigForRequest->dataPerRequest->spawnedProcessId);
            if (
                $verifySpawnedProcessIdResponse->getStatusCode() !== HttpConsts::STATUS_OK
                || $request->getUri()->getPath() === HttpServerHandle::STATUS_CHECK_URI
            ) {
                return $verifySpawnedProcessIdResponse;
            }
        }

        return $this->processRequest($request);
    }

    protected static function getRequestHeader(ServerRequestInterface $request, string $headerName): ?string
    {
        $headerValues = $request->getHeader($headerName);
        if (empty($headerValues)) {
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
        if (is_null($headerValue)) {
            throw new RuntimeException('Missing required HTTP request header `' . $headerName . '\'');
        }
        return $headerValue;
    }
}
