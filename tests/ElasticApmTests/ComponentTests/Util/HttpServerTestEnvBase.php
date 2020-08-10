<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests\Util;

use Elastic\Apm\Impl\Constants;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Util\DbgUtil;
use Elastic\Apm\Tests\Util\TestLogCategory;
use Elastic\Apm\TransactionInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

abstract class HttpServerTestEnvBase extends TestEnvBase
{
    /** @var Logger */
    private $logger;

    /** @var int */
    protected $appCodeHostServerPort;

    public function __construct()
    {
        parent::__construct();

        $this->logger = AmbientContext::loggerFactory()->loggerForClass(
            TestLogCategory::TEST_UTIL,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        )->addContext('this', $this);
    }

    protected function sendRequestToInstrumentedApp(TestProperties $testProperties): void
    {
        $this->ensureMockApmServerStarted();
        $this->ensureAppCodeHostServerStarted($testProperties);

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Sending HTTP request `' . $testProperties->httpMethod . ' ' . $testProperties->uriPath . '\''
            . ' to ' . DbgUtil::fqToShortClassName(BuiltinHttpServerAppCodeHost::class) . '...'
        );

        /** @noinspection PhpUnhandledExceptionInspection */
        $response = BuiltinHttpServerAppCodeHost::sendRequest(
            $this->appCodeHostServerPort,
            $testProperties->httpMethod,
            $testProperties->uriPath,
            [
                self::TEST_ENV_ID_HEADER_NAME => $this->testEnvId(),
                BuiltinHttpServerAppCodeHost::CLASS_HEADER_NAME  => $testProperties->appCodeClass,
                BuiltinHttpServerAppCodeHost::METHOD_HEADER_NAME => $testProperties->appCodeMethod,
            ]
        );
        if ($response->getStatusCode() !== $testProperties->expectedStatusCode) {
            throw new RuntimeException(
                'HTTP status code does not match the expected one.'
                . ' Expected:' . $testProperties->expectedStatusCode
                . ', actual:' . $response->getStatusCode()
            );
        }

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Successfully sent HTTP request `' . $testProperties->httpMethod . ' ' . $testProperties->uriPath . '\''
            . ' to ' . DbgUtil::fqToShortClassName(BuiltinHttpServerAppCodeHost::class) . '...'
        );
    }

    abstract protected function ensureAppCodeHostServerStarted(TestProperties $testProperties): void;

    protected function verifyRootTransactionName(
        TestProperties $testProperties,
        TransactionInterface $rootTransaction
    ): void {
        parent::verifyRootTransactionName($testProperties, $rootTransaction);

        if (is_null($testProperties->transactionName)) {
            TestCase::assertSame(
                $testProperties->httpMethod . ' ' . $testProperties->uriPath,
                $rootTransaction->getName()
            );
        }
    }

    protected function verifyRootTransactionType(
        TestProperties $testProperties,
        TransactionInterface $rootTransaction
    ): void {
        parent::verifyRootTransactionType($testProperties, $rootTransaction);

        if (is_null($testProperties->transactionType)) {
            TestCase::assertSame(Constants::TRANSACTION_TYPE_REQUEST, $rootTransaction->getType());
        }
    }
}
