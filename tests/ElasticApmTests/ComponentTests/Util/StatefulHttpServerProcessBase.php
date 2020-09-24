<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests\Util;

use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Util\DbgUtil;
use Elastic\Apm\Impl\Util\ObjectToStringBuilder;
use Elastic\Apm\Tests\Util\TestLogCategory;
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

    /** @var string */
    protected $runScriptFile;

    /** @var int */
    protected $port;

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

    protected function processConfig(): void
    {
        parent::processConfig();

        $this->port = intval(
            self::getRequiredTestOption(
                AllComponentTestsOptionsMetadata::THIS_SERVER_PORT_OPTION_NAME
            )
        );

        self::getRequiredTestOption(AllComponentTestsOptionsMetadata::THIS_SERVER_ID_OPTION_NAME);
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface|Promise
     */
    abstract protected function processRequest(ServerRequestInterface $request);

    protected function runImpl(): void
    {
        try {
            $this->runHttpServer($this->port);
        } catch (Exception $ex) {
            ($loggerProxy = $this->logger->ifNoticeLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->logThrowable($ex, 'Failed to start HTTP server - exiting...');
        }
    }

    private function runHttpServer(int $port): void
    {
        $loop = Factory::create();

        $serverSocket = new ServerSocket($port, $loop);

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

    protected function shouldRequestHaveServerId(ServerRequestInterface $request): bool
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
            $verifyTestEnvIdResponse = self::verifyServerIdEx(
                function (string $headerName) use ($request): string {
                    return self::getRequestHeader($request, $headerName);
                }
            );
            if (
                $verifyTestEnvIdResponse->getStatusCode() !== HttpConsts::STATUS_OK
                || $request->getUri()->getPath() === TestEnvBase::STATUS_CHECK_URI
            ) {
                return $verifyTestEnvIdResponse;
            }
        }

        return $this->processRequest($request);
    }

    protected static function getRequestHeader(ServerRequestInterface $request, string $headerName): string
    {
        $headerValues = $request->getHeader($headerName);
        if (empty($headerValues)) {
            throw new RuntimeException('Missing required HTTP request header `' . $headerName . '\'');
        }
        if (count($headerValues) != 1) {
            throw new RuntimeException(
                "Header `$headerName\' should not have more than one value."
                . ' Instead found: ' . DbgUtil::formatArray($headerValues)
            );
        }
        return $headerValues[0];
    }

    protected function toStringAddProperties(ObjectToStringBuilder $builder): void
    {
        parent::toStringAddProperties($builder);
        $builder->add('port', $this->port);
    }
}
