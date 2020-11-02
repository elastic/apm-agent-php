<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests\Util;

use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Util\DbgUtil;
use Elastic\Apm\Tests\Util\LogCategoryForTests;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use React\Http\Server as HttpServer;
use React\Promise\Promise;
use React\Socket\Server as ServerSocket;
use RuntimeException;
use Throwable;

abstract class StatefulHttpServerProcessBase extends CliProcessBase
{
    use HttpServerProcessTrait;

    /** @var Logger */
    private $logger;

    public function __construct()
    {
        parent::__construct();

        $this->logger = AmbientContext::loggerFactory()->loggerForClass(
            LogCategoryForTests::TEST_UTIL,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        )->addContext('this', $this);
    }

    protected function processConfig(): void
    {
        parent::processConfig();

        TestAssertUtil::assertThat(
            !is_null(AmbientContext::config()->sharedDataPerProcess->thisServerId),
            strval(AmbientContext::config())
        );
        TestAssertUtil::assertThat(
            !is_null(AmbientContext::config()->sharedDataPerProcess->thisServerPort),
            strval(AmbientContext::config())
        );

        TestAssertUtil::assertThat(
            !isset(AmbientContext::config()->sharedDataPerRequest->serverId),
            strval(AmbientContext::config())
        );
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
            function (CliProcessBase $thisObjArg): void {
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
            ($loggerProxy = $this->logger->ifNoticeLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->logThrowable($ex, 'Failed to start HTTP server - exiting...');
        }
    }

    private function runHttpServer(): void
    {
        $loop = Factory::create();

        $serverSocket = new ServerSocket(AmbientContext::config()->sharedDataPerProcess->thisServerPort, $loop);

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

    protected function shouldRequestHaveServerId(
        /** @noinspection PhpUnusedParameterInspection */ ServerRequestInterface $request
    ): bool {
        return true;
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface|Promise
     */
    private function processRequestWrapper(ServerRequestInterface $request)
    {
        ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Received request',
            ['URI' => $request->getUri(), 'method' => $request->getMethod(), 'target' => $request->getRequestTarget()]
        );

        try {
            $response = $this->processRequestWrapperImpl($request);

            if ($response instanceof ResponseInterface) {
                ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
                && $loggerProxy->log(
                    'Sending response ...',
                    ['statusCode' => $response->getStatusCode(), 'reasonPhrase' => $response->getReasonPhrase()]
                );
            } else {
                assert($response instanceof Promise);

                ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
                && $loggerProxy->log('Promise returned - response will be returned later...');
            }

            return $response;
        } catch (Throwable $throwable) {
            ($loggerProxy = $this->logger->ifCriticalLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'processRequest() exited by exception - terminating this process',
                ['$throwable' => $throwable]
            );
            exit(1);
        }
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface|Promise
     */
    private function processRequestWrapperImpl(ServerRequestInterface $request)
    {
        if ($this->shouldRequestHaveServerId($request)) {
            $testConfigForRequest = TestConfigUtil::read(
                AmbientContext::dbgProcessName(),
                new RequestHeadersRawSnapshotSource(
                    function (string $headerName) use ($request): ?string {
                        return self::getRequestHeader($request, $headerName);
                    }
                )
            );
            $verifyServerIdResponse = self::verifyServerId($testConfigForRequest->sharedDataPerRequest->serverId);
            if (
                $verifyServerIdResponse->getStatusCode() !== HttpConsts::STATUS_OK
                || $request->getUri()->getPath() === TestEnvBase::STATUS_CHECK_URI
            ) {
                return $verifyServerIdResponse;
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
        if (count($headerValues) != 1) {
            throw new RuntimeException(
                "Header `$headerName\' should not have more than one value."
                . ' Instead found: ' . DbgUtil::formatArray($headerValues)
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
