<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests\Util;

use Closure;
use Elastic\Apm\Impl\Clock;
use Elastic\Apm\Impl\Config\EnvVarsRawSnapshotSource;
use Elastic\Apm\Impl\Config\OptionNames;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\MetadataDiscoverer;
use Elastic\Apm\Impl\Tracer;
use Elastic\Apm\Impl\Util\DbgUtil;
use Elastic\Apm\Impl\Util\ObjectToStringBuilder;
use Elastic\Apm\Tests\Util\TestCaseBase;
use Elastic\Apm\Tests\Util\TestLogCategory;
use Elastic\Apm\TransactionDataInterface;
use GuzzleHttp\Exception\ConnectException;
use PHPUnit\Exception as PhpUnitException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

abstract class TestEnvBase
{
    private const PORTS_RANGE_BEGIN = 50000;
    private const PORTS_RANGE_END = 60000;

    public const STATUS_CHECK_URI = '/elastic_apm_php_tests_status_check';
    public const HEADER_NAME_PREFIX = 'ELASTIC_APM_PHP_TESTS_';
    public const TEST_ENV_ID_HEADER_NAME = self::HEADER_NAME_PREFIX . 'TEST_ENV_ID';

    public const DATA_FROM_AGENT_MAX_WAIT_TIME_SECONDS = 10;

    private const AUTH_HTTP_HEADER_NAME = 'Authorization';

    /** @var int|null */
    protected $spawnedProcessesCleanerPort = null;

    /** @var Logger */
    private $logger;

    /** @var int|null */
    private $mockApmServerPort = null;

    /** @var DataFromAgent */
    private $dataFromAgent;

    public function __construct()
    {
        $this->logger = AmbientContext::loggerFactory()->loggerForClass(
            TestLogCategory::TEST_UTIL,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        )->addContext('this', $this);

        $this->dataFromAgent = new DataFromAgent();
    }

    public function testEnvId(): string
    {
        return PhpUnitExtension::$testEnvId;
    }

    protected function ensureMockApmServerRunning(): void
    {
        $this->ensureSpawnedProcessesCleanerRunning();

        if (!is_null($this->mockApmServerPort)) {
            return;
        }

        $mockApmServerPort
            = AmbientContext::config()->mockApmServerPort() === AllComponentTestsOptionsMetadata::INT_OPTION_NOT_SET
                ? $this->findFreePortToListen()
                : AmbientContext::config()->mockApmServerPort();

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Starting ' . DbgUtil::fqToShortClassName(MockApmServer::class) . '...',
            ['mockApmServerPort' => $mockApmServerPort]
        );
        TestProcessUtil::startBackgroundProcess(
            "php"
            . ' "' . __DIR__ . DIRECTORY_SEPARATOR . 'runMockApmServer.php"'
            . ' --' . StatefulHttpServerProcessBase::PORT_CMD_OPT_NAME . '=' . $mockApmServerPort,
            $this->buildEnvVars()
        );
        $this->ensureHttpServerRunning(
            $mockApmServerPort,
            /* dbgServerDesc */ DbgUtil::fqToShortClassName(MockApmServer::class)
        );

        $this->mockApmServerPort = $mockApmServerPort;
    }

    protected function findFreePortToListen(): int
    {
        return mt_rand(self::PORTS_RANGE_BEGIN, self::PORTS_RANGE_END - 1);
    }

    private function ensureSpawnedProcessesCleanerRunning(): void
    {
        if (!is_null($this->spawnedProcessesCleanerPort)) {
            return;
        }

        $spawnedProcessesCleanerPort = $this->findFreePortToListen();

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Starting ' . DbgUtil::fqToShortClassName(SpawnedProcessesCleaner::class) . '...',
            ['spawnedProcessesCleanerPort' => $spawnedProcessesCleanerPort]
        );
        TestProcessUtil::startBackgroundProcess(
            "php"
            . ' "' . __DIR__ . '/runSpawnedProcessesCleaner.php"'
            . ' --' . StatefulHttpServerProcessBase::PORT_CMD_OPT_NAME . '=' . $spawnedProcessesCleanerPort
            . ' --' . SpawnedProcessesCleaner::PARENT_PID_CMD_OPT_NAME . '=' . getmypid(),
            $this->buildEnvVars()
        );
        $this->ensureHttpServerRunning(
            $spawnedProcessesCleanerPort,
            /* dbgServerDesc */ DbgUtil::fqToShortClassName(SpawnedProcessesCleaner::class)
        );
        $this->spawnedProcessesCleanerPort = $spawnedProcessesCleanerPort;
    }

    protected function ensureHttpServerRunning(int $port, string $dbgServerDesc): void
    {
        $checkRetVal = (new PollingCheck(
            $dbgServerDesc . ' started',
            10 * 1000 * 1000 /* maxWaitTimeInMicroseconds - 10 seconds */,
            AmbientContext::loggerFactory()
        ))->run(
            function () use ($port, $dbgServerDesc) {
                try {
                    /** @phpstan-ignore-next-line */
                    HttpServerProcessTrait::sendRequestToCheckStatus($port, $this->testEnvId(), $dbgServerDesc);
                } catch (ConnectException $ex) {
                    return false;
                }
                return true;
            }
        );

        if ($checkRetVal) {
            return;
        }

        throw new RuntimeException("HTTP Server is not running. dbgServerDesc: $dbgServerDesc. port: $port.");
    }

    /**
     * @param array<string, string> $additionalEnvVars
     *
     * @return array<string, string>
     */
    protected function buildEnvVars(array $additionalEnvVars = []): array
    {
        ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Entered', ['additionalEnvVars' => $additionalEnvVars]);

        /** @var array<string, string> */
        $result = getenv();

        if (!is_null($this->mockApmServerPort)) {
            $result[EnvVarsRawSnapshotSource::optionNameToEnvVarName(
                EnvVarsRawSnapshotSource::DEFAULT_PREFIX,
                OptionNames::SERVER_URL
            )]
                = 'http://localhost:' . $this->mockApmServerPort;
        }

        if (!is_null($this->spawnedProcessesCleanerPort)) {
            $result[EnvVarsRawSnapshotSource::optionNameToEnvVarName(
                AmbientContext::ENV_VAR_NAME_PREFIX,
                AllComponentTestsOptionsMetadata::SPAWNED_PROCESSES_CLEANER_PORT_OPTION_NAME
            )]
                = strval($this->spawnedProcessesCleanerPort);
        }

        $result[EnvVarsRawSnapshotSource::optionNameToEnvVarName(
            AmbientContext::ENV_VAR_NAME_PREFIX,
            AllComponentTestsOptionsMetadata::TEST_ENV_ID_OPTION_NAME
        )]
            = $this->testEnvId();

        $result = array_merge($result, $additionalEnvVars);

        ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Exiting', ['result' => $result]);

        return $result;
    }

    /**
     * @param TestProperties $testProperties
     * @param Closure        $verifyFunc
     *
     * @return void
     *
     * @phpstan-param Closure(DataFromAgent): void $verifyFunc
     */
    public function sendRequestToInstrumentedAppAndVerifyDataFromAgent(
        TestProperties $testProperties,
        Closure $verifyFunc
    ): void {
        try {
            $this->dataFromAgent->clearAdded();
            $timeBeforeRequestToApp = Clock::singletonInstance()->getSystemClockCurrentTime();
            $this->sendRequestToInstrumentedApp($testProperties);
            $this->pollDataFromAgentAndVerify($timeBeforeRequestToApp, $testProperties, $verifyFunc);
        } finally {
            $testProperties->tearDown();
        }
    }

    abstract protected function sendRequestToInstrumentedApp(TestProperties $testProperties): void;

    public function shutdown(): void
    {
        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Shutting down...');

        $this->signalSpawnedProcessesCleanerToExit();
    }

    private function signalSpawnedProcessesCleanerToExit(): void
    {
        if (is_null($this->spawnedProcessesCleanerPort)) {
            return;
        }

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Signaling ' . DbgUtil::fqToShortClassName(SpawnedProcessesCleaner::class) . ' to clean and exit...'
        );

        SpawnedProcessesCleaner::sendRequestToCleanAndExit($this->spawnedProcessesCleanerPort, $this->testEnvId());

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Signaled ' . DbgUtil::fqToShortClassName(SpawnedProcessesCleaner::class) . ' to clean and exit'
        );
    }

    /**
     * @param float          $timeBeforeRequestToApp
     * @param TestProperties $testProperties
     * @param Closure        $verifyFunc
     *
     * @return void
     *
     * @phpstan-param Closure(DataFromAgent): void $verifyFunc
     */
    public function pollDataFromAgentAndVerify(
        float $timeBeforeRequestToApp,
        TestProperties $testProperties,
        Closure $verifyFunc
    ): void {
        /** @var PhpUnitException|null */
        $lastException = null;
        $lastCheckedNextIntakeApiRequestIndex = $this->dataFromAgent->nextIntakeApiRequestIndexToFetch();
        $hasPassed = (new PollingCheck(
            __FUNCTION__ . ' passes',
            self::DATA_FROM_AGENT_MAX_WAIT_TIME_SECONDS * 1000 * 1000 /* maxWaitTimeInMicroseconds */,
            AmbientContext::loggerFactory()
        ))->run(
            function () use (
                $timeBeforeRequestToApp,
                $testProperties,
                $verifyFunc,
                &$lastException,
                &$lastCheckedNextIntakeApiRequestIndex
            ) {
                $this->ensureLatestDataFromMockApmServer($timeBeforeRequestToApp);
                $currentNextIntakeApiRequestIndex = $this->dataFromAgent->nextIntakeApiRequestIndexToFetch();
                if (
                    !is_null($lastException)
                    && ($currentNextIntakeApiRequestIndex === $lastCheckedNextIntakeApiRequestIndex)
                ) {
                    // No new data since the last check - there's no point in invoking $verifyFunc() again
                    return false;
                }

                $lastCheckedNextIntakeApiRequestIndex = $currentNextIntakeApiRequestIndex;
                try {
                    $this->verifyDataAgainstRequest($testProperties);
                    $verifyFunc($this->dataFromAgent);
                } catch (PhpUnitException $ex) {
                    $lastException = $ex;
                    return false;
                }
                return true;
            }
        );

        if (!$hasPassed) {
            assert(!is_null($lastException));

            ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                __FUNCTION__ . ' failed.',
                ['last exception from verifyFunc()' => $lastException]
            );

            throw $lastException;
        }
    }

    private function ensureLatestDataFromMockApmServer(float $timeBeforeRequestToApp): void
    {
        try {
            $newIntakeApiRequests = $this->fetchLatestDataFromMockApmServer();
            if (!empty($newIntakeApiRequests)) {
                $this->dataFromAgent->addIntakeApiRequests($newIntakeApiRequests, $timeBeforeRequestToApp);
            }
            return;
        } catch (Throwable $thrown) {
            ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Failed to process data from the agent',
                ['thrown' => $thrown]
            );
            /** @noinspection PhpUnhandledExceptionInspection */
            throw $thrown;
        }
    }

    protected function verifyDataAgainstRequest(TestProperties $testProperties): void
    {
        $this->verifyHttpRequestHeaders($testProperties);

        $this->verifyMetadata($testProperties);

        $rootTransaction = TestCaseBase::findRootTransaction($this->dataFromAgent->idToTransaction);
        $this->verifyRootTransactionName($testProperties, $rootTransaction);
        $this->verifyRootTransactionType($testProperties, $rootTransaction);

        TestCaseBase::assertValidTransactionsAndSpans(
            $this->dataFromAgent->idToTransaction,
            $this->dataFromAgent->idToSpan
        );
    }

    protected function verifyHttpRequestHeaders(TestProperties $testProperties): void
    {
        $this->verifyAuthHttpRequestHeaders(
            $testProperties->configuredApiKey /* <- expectedApiKey */,
            /* expectedSecretToken: */
            is_null($testProperties->configuredApiKey)
                ? $testProperties->configuredSecretToken
                : null,
            $this->dataFromAgent
        );
    }

    public static function verifyAuthHttpRequestHeaders(
        ?string $expectedApiKey,
        ?string $expectedSecretToken,
        DataFromAgent $dataFromAgent
    ): void {
        if (!is_null($expectedApiKey)) {
            TestCase::assertNull($expectedSecretToken);
        }

        $expectedAuthHeaderValue = is_null($expectedApiKey)
            ? ( is_null($expectedSecretToken) ? null : "Bearer $expectedSecretToken")
            : "ApiKey $expectedApiKey";

        foreach ($dataFromAgent->intakeApiRequests as $intakeApiRequest) {
            if (is_null($expectedAuthHeaderValue)) {
                TestCase::assertArrayNotHasKey(self::AUTH_HTTP_HEADER_NAME, $intakeApiRequest->headers);
            } else {
                $actualAuthHeaderValue = $intakeApiRequest->headers[self::AUTH_HTTP_HEADER_NAME];
                TestCase::assertCount(1, $actualAuthHeaderValue);
                TestCase::assertSame($expectedAuthHeaderValue, $actualAuthHeaderValue[0]);
            }
        }
    }

    protected function verifyMetadata(TestProperties $testProperties): void
    {
        $expectedEnvironment = Tracer::limitNullableKeywordString($testProperties->configuredEnvironment);
        self::verifyEnvironment($expectedEnvironment, $this->dataFromAgent);

        $expectedServiceName = is_null($testProperties->configuredServiceName)
            ? MetadataDiscoverer::DEFAULT_SERVICE_NAME
            : MetadataDiscoverer::adaptServiceName($testProperties->configuredServiceName);
        self::verifyServiceName($expectedServiceName, $this->dataFromAgent);

        $expectedServiceVersion = Tracer::limitNullableKeywordString($testProperties->configuredServiceVersion);
        self::verifyServiceVersion($expectedServiceVersion, $this->dataFromAgent);
    }

    public static function verifyEnvironment(?string $expected, DataFromAgent $dataFromAgent): void
    {
        foreach ($dataFromAgent->metadata as $metadata) {
            TestCase::assertSame($expected, $metadata->service()->environment());
        }
    }

    public static function verifyServiceName(string $expected, DataFromAgent $dataFromAgent): void
    {
        foreach ($dataFromAgent->metadata as $metadata) {
            TestCase::assertSame($expected, $metadata->service()->name());
        }
    }

    public static function verifyServiceVersion(?string $expected, DataFromAgent $dataFromAgent): void
    {
        foreach ($dataFromAgent->metadata as $metadata) {
            TestCase::assertSame($expected, $metadata->service()->version());
        }
    }

    protected function verifyRootTransactionName(
        TestProperties $testProperties,
        TransactionDataInterface $rootTransaction
    ): void {
        if (!is_null($testProperties->transactionName)) {
            TestCase::assertSame($testProperties->transactionName, $rootTransaction->getName());
        }
    }

    protected function verifyRootTransactionType(
        TestProperties $testProperties,
        TransactionDataInterface $rootTransaction
    ): void {
        if (!is_null($testProperties->transactionType)) {
            TestCase::assertSame($testProperties->transactionType, $rootTransaction->getType());
        }
    }

    /**
     * @return IntakeApiRequest[]
     */
    private function fetchLatestDataFromMockApmServer(): array
    {
        TestCase::assertNotNull($this->mockApmServerPort);

        $response = MockApmServer::sendRequestToGetAgentIntakeApiRequests(
            $this->mockApmServerPort,
            $this->testEnvId(),
            $this->dataFromAgent->nextIntakeApiRequestIndexToFetch()
        );
        $decodedBody = json_decode($response->getBody()->getContents(), /* assoc */ true);

        $requestsJson = $decodedBody[MockApmServer::INTAKE_API_REQUESTS_JSON_KEY];
        $newIntakeApiRequests = [];
        foreach ($requestsJson as $requestJson) {
            $newIntakeApiRequests[] = IntakeApiRequest::jsonDeserialize($requestJson);
        }

        if (!empty($newIntakeApiRequests)) {
            ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Fetched new intake API requests received from agent',
                ['newIntakeApiRequestsCount' => count($newIntakeApiRequests)]
            );
        }
        return $newIntakeApiRequests;
    }

    public function __toString(): string
    {
        $builder = new ObjectToStringBuilder(DbgUtil::fqToShortClassName(get_called_class()));
        $builder->add('testEnvId', $this->testEnvId());
        return $builder->build();
    }
}
