<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests\Util;

use Elastic\Apm\ElasticApm;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Tests\Util\LogCategoryForTests;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

final class BuiltinHttpServerAppCodeHost extends AppCodeHostBase
{
    use HttpServerProcessTrait;

    /** @var Logger */
    private $logger;

    public function __construct()
    {
        if (self::isStatusCheck()) {
            // We don't want any of the testing infrastructure operations to be recorded as application's APM events
            ElasticApm::getCurrentTransaction()->discard();
        }

        parent::__construct();

        $this->logger = AmbientContext::loggerFactory()->loggerForClass(
            LogCategoryForTests::TEST_UTIL,
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

    protected function shouldRegisterThisProcessWithResourcesCleaner(): bool
    {
        // We should register with ResourcesCleaner only on the status-check request
        return self::isStatusCheck();
    }

    protected function processConfig(): void
    {
        TestAssertUtil::assertThat(
            !is_null(AmbientContext::config()->sharedDataPerProcess->thisServerId),
            strval(AmbientContext::config())
        );
        TestAssertUtil::assertThat(
            !is_null(AmbientContext::config()->sharedDataPerProcess->thisServerPort),
            strval(AmbientContext::config())
        );

        parent::processConfig();

        AmbientContext::reconfigure(
            new RequestHeadersRawSnapshotSource(
                function (string $headerName): ?string {
                    $headerKey = 'HTTP_' . $headerName;
                    return array_key_exists($headerKey, $_SERVER) ? $_SERVER[$headerKey] : null;
                }
            )
        );
    }

    protected function runImpl(): void
    {
        $response = self::verifyServerId(AmbientContext::config()->sharedDataPerRequest->serverId);
        if ($response->getStatusCode() !== HttpConsts::STATUS_OK || self::isStatusCheck()) {
            self::sendResponse($response);
            return;
        }

        $this->callAppCode();
    }

    private static function sendResponse(ResponseInterface $response): void
    {
        http_response_code($response->getStatusCode());
        echo $response->getBody();
    }
}
