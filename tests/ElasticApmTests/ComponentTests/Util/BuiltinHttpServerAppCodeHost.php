<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests\Util;

use Elastic\Apm\ElasticApm;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Tests\Util\TestLogCategory;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

final class BuiltinHttpServerAppCodeHost extends AppCodeHostBase
{
    use HttpServerProcessTrait;

    public const CLASS_HEADER_NAME = TestEnvBase::HEADER_NAME_PREFIX . 'APP_CODE_CLASS';
    public const METHOD_HEADER_NAME = TestEnvBase::HEADER_NAME_PREFIX . 'APP_CODE_METHOD';

    /** @var Logger */
    private $logger;

    public function __construct(string $runScriptFile)
    {
        if (self::isStatusCheck()) {
            // We don't want any of the infrastructure operations to be recorded as application's APM events
            ElasticApm::getCurrentTransaction()->discard();
        }

        parent::__construct($runScriptFile);

        $this->logger = AmbientContext::loggerFactory()->loggerForClass(
            TestLogCategory::TEST_UTIL,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        )->addContext('this', $this);

        ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Received request',
            ['URI' => $_SERVER['REQUEST_URI'], 'method' => $_SERVER['REQUEST_METHOD']]
        );
    }

    protected static function isStatusCheck(): bool
    {
        return $_SERVER['REQUEST_URI'] === TestEnvBase::STATUS_CHECK_URI;
    }

    protected function shouldRegisterWithSpawnedProcessesCleaner(): bool
    {
        // We should register with SpawnedProcessesCleaner only on the status-check request
        return self::isStatusCheck();
    }

    protected function parseArgs(): void
    {
        if (!self::isStatusCheck()) {
            $this->appCodeClass = self::getRequestHeader(self::CLASS_HEADER_NAME);
            $this->appCodeMethod = self::getRequestHeader(self::METHOD_HEADER_NAME);
        }
    }

    protected function runImpl(): void
    {
        $response = self::verifyTestEnvIdEx([__CLASS__, 'getRequestHeader']);
        if ($response->getStatusCode() !== HttpConsts::STATUS_OK) {
            self::sendResponse($response);
            return;
        }

        if (!self::isStatusCheck()) {
            $this->callAppCode();
        }
    }

    private static function sendResponse(ResponseInterface $response): void
    {
        http_response_code($response->getStatusCode());
        echo $response->getBody();
    }

    protected function cliHelpOptions(): string
    {
        throw new RuntimeException('This method should not be called: ' . __METHOD__);
    }

    protected static function getRequestHeader(string $headerName): string
    {
        $headerKey = 'HTTP_' . $headerName;
        if (!array_key_exists($headerKey, $_SERVER)) {
            throw new RuntimeException('Required HTTP request header `' . $headerName . '\' is missing');
        }

        return $_SERVER[$headerKey];
    }
}
