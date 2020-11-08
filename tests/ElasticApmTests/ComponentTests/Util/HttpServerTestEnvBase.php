<?php

declare(strict_types=1);

namespace ElasticApmTests\ComponentTests\Util;

use Elastic\Apm\Impl\Constants;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\TransactionData;
use Elastic\Apm\Impl\Util\ClassNameUtil;
use ElasticApmTests\Util\LogCategoryForTests;
use PHPUnit\Framework\TestCase;
use RuntimeException;

abstract class HttpServerTestEnvBase extends TestEnvBase
{
    /** @var string|null */
    protected $appCodeHostServerId = null;

    /** @var int|null */
    protected $appCodeHostServerPort = null;

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

    public function isHttp(): bool
    {
        return true;
    }

    protected function sendRequestToInstrumentedApp(TestProperties $testProperties): void
    {
        $this->ensureAppCodeHostServerRunning($testProperties);
        TestCase::assertNotNull($this->appCodeHostServerPort);
        TestCase::assertNotNull($this->appCodeHostServerId);

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Sending HTTP request `' . $testProperties->httpMethod . ' ' . $testProperties->uriPath . '\''
            . ' to ' . ClassNameUtil::fqToShort(BuiltinHttpServerAppCodeHost::class) . '...'
        );

        /** @noinspection PhpUnhandledExceptionInspection */
        $response = TestHttpClientUtil::sendHttpRequest(
            $this->appCodeHostServerPort,
            $testProperties->httpMethod,
            $testProperties->uriPath,
            SharedDataPerRequest::fromServerId($this->appCodeHostServerId, $testProperties->sharedDataPerRequest)
        );
        if ($response->getStatusCode() !== $testProperties->expectedStatusCode) {
            throw new RuntimeException(
                'HTTP status code does not match the expected one.'
                . ' Expected: ' . $testProperties->expectedStatusCode
                . ', actual: ' . $response->getStatusCode()
            );
        }

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Successfully sent HTTP request `' . $testProperties->httpMethod . ' ' . $testProperties->uriPath . '\''
            . ' to ' . ClassNameUtil::fqToShort(BuiltinHttpServerAppCodeHost::class) . '...'
        );
    }

    abstract protected function ensureAppCodeHostServerRunning(TestProperties $testProperties): void;

    protected function verifyRootTransactionName(
        TestProperties $testProperties,
        TransactionData $rootTransaction
    ): void {
        parent::verifyRootTransactionName($testProperties, $rootTransaction);

        if (is_null($testProperties->expectedTransactionName)) {
            TestCase::assertSame(
                $testProperties->httpMethod . ' ' . $testProperties->uriPath,
                $rootTransaction->name
            );
        }
    }

    protected function verifyRootTransactionType(
        TestProperties $testProperties,
        TransactionData $rootTransaction
    ): void {
        parent::verifyRootTransactionType($testProperties, $rootTransaction);

        if (is_null($testProperties->transactionType)) {
            TestCase::assertSame(Constants::TRANSACTION_TYPE_REQUEST, $rootTransaction->type);
        }
    }
}
