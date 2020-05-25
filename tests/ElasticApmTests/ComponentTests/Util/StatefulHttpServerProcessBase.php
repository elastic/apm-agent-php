<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests\Util;

use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Util\DbgUtil;
use Elastic\Apm\Impl\Util\ObjectToStringBuilder;
use Elastic\Apm\Tests\Util\TestLogCategory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use React\Http\Server as HttpServer;
use React\Socket\Server as ServerSocket;
use RuntimeException;
use Throwable;

abstract class StatefulHttpServerProcessBase extends CliProcessBase
{
    use HttpServerProcessTrait;

    public const PORT_CMD_OPT_NAME = 'port';

    /** @var string */
    protected $runScriptFile;

    /** @var int */
    protected $port;

    /** @var Logger */
    private $logger;

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

    protected function cliHelpOptions(): string
    {
        return ' --' . self::PORT_CMD_OPT_NAME . /** @lang text */ '=<port number>';
    }

    protected function parseArgs(): void
    {
        $longOpts = [];

        // --port=12345 - required value
        $longOpts[] = self::PORT_CMD_OPT_NAME . ':';

        $parsedCliOptions = getopt(/* shortOpts */ '', $longOpts);

        $portAsString = $this->checkRequiredCliOption(self::PORT_CMD_OPT_NAME, $parsedCliOptions);
        $this->port = intval($portAsString);
    }

    abstract protected function processRequest(ServerRequestInterface $request): ResponseInterface;

    protected function runImpl(): void
    {
        $this->runHttpServer($this->port);
    }

    private function runHttpServer(int $port): void
    {
        $loop = Factory::create();

        $serverSocket = new ServerSocket($port, $loop);

        $httpServer = new HttpServer(
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

    protected function shouldHaveTestEnvId(ServerRequestInterface $request): bool
    {
        return true;
    }

    private function processRequestWrapper(ServerRequestInterface $request): ResponseInterface
    {
        ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Received request',
            ['URI' => $request->getUri(), 'method' => $request->getMethod(), 'target' => $request->getRequestTarget()]
        );

        try {
            $response = $this->processRequestWrapperImpl($request);

            ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Sending response ...',
                ['status code' => $response->getStatusCode(), 'reason phrase' => $response->getReasonPhrase()]
            );

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

    private function processRequestWrapperImpl(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->shouldHaveTestEnvId($request)) {
            $verifyTestEnvIdResponse = self::verifyTestEnvIdEx(
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
