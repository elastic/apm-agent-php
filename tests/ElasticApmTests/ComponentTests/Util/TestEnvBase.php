<?php

/** @noinspection PhpDocMissingThrowsInspection, PhpUnhandledExceptionInspection, PhpUndefinedClassInspection */

declare(strict_types=1);

namespace ElasticApmTests\ComponentTests\Util;

use Closure;
use Elastic\Apm\Impl\BackendComm\SerializationUtil;
use Elastic\Apm\Impl\Clock;
use Elastic\Apm\Impl\Config\EnvVarsRawSnapshotSource;
use Elastic\Apm\Impl\Config\OptionNames;
use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableTrait;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\MetadataDiscoverer;
use Elastic\Apm\Impl\Tracer;
use Elastic\Apm\Impl\TransactionData;
use Elastic\Apm\Impl\Util\ClassNameUtil;
use Elastic\Apm\Impl\Util\IdGenerator;
use Elastic\Apm\Impl\Util\JsonUtil;
use Elastic\Apm\Impl\Util\TextUtil;
use ElasticApmTests\Util\LogCategoryForTests;
use ElasticApmTests\Util\TestCaseBase;
use Exception;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use PHPUnit\Exception as PhpUnitException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

abstract class TestEnvBase implements LoggableInterface
{
    use LoggableTrait;

    private const PORTS_RANGE_BEGIN = 50000;
    private const PORTS_RANGE_END = 60000;

    private const MAX_WAIT_SERVER_START_MICROSECONDS = 10 * 1000 * 1000; // 10 seconds
    private const MAX_TRIES_TO_START_SERVER = 3;

    public const STATUS_CHECK_URI = '/elastic_apm_php_tests_status_check';

    public const DATA_FROM_AGENT_MAX_WAIT_TIME_SECONDS = 10;

    private const AUTH_HTTP_HEADER_NAME = 'Authorization';

    /** @var int|null */
    protected $resourcesCleanerPort = null;

    /** @var string|null */
    protected $resourcesCleanerServerId = null;

    /** @var string|null */
    protected $mockApmServerId = null;

    /** @var int|null */
    private $mockApmServerPort = null;

    /** @var Logger */
    private $logger;

    /** @var DataFromAgent */
    private $dataFromAgent;

    public function __construct()
    {
        $this->logger = AmbientContext::loggerFactory()->loggerForClass(
            LogCategoryForTests::TEST_UTIL,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        )->addContext('this', $this);

        $this->dataFromAgent = new DataFromAgent();
    }

    public function generateSecondaryIdFromTestEnvId(): string
    {
        return PhpUnitExtension::$testEnvId . '_' . IdGenerator::generateId(/* idLengthInBytes */ 16);
    }

    protected function findFreePortToListen(): int
    {
        return mt_rand(self::PORTS_RANGE_BEGIN, self::PORTS_RANGE_END - 1);
    }

    protected function isHttpServerRunning(int $port, string $serverId, string $dbgServerDesc): bool
    {
        /** @var Throwable|null */
        $lastException = null;
        $checkResult = (new PollingCheck(
            $dbgServerDesc . ' started',
            self::MAX_WAIT_SERVER_START_MICROSECONDS,
            AmbientContext::loggerFactory()
        ))->run(
            function () use ($port, $serverId, $dbgServerDesc, &$lastException) {
                $logger = AmbientContext::loggerFactory()->loggerForClass(
                    LogCategoryForTests::TEST_UTIL,
                    __NAMESPACE__,
                    __CLASS__,
                    __FILE__
                )->addAllContext(['dbgServerDesc' => $dbgServerDesc, 'port' => $port, 'serverId' => $serverId]);

                try {
                    $response = TestHttpClientUtil::sendHttpRequest(
                        $port,
                        HttpConsts::METHOD_GET,
                        TestEnvBase::STATUS_CHECK_URI,
                        SharedDataPerRequest::fromServerId($serverId)
                    );
                } catch (Throwable $throwable) {
                    $lastException = $throwable;
                    return false;
                }

                if ($response->getStatusCode() !== HttpConsts::STATUS_OK) {
                    ($loggerProxy = $logger->ifNoticeLevelEnabled(__LINE__, __FUNCTION__))
                    && $loggerProxy->log(
                        'Received non-OK status code in response to status check',
                        ['receivedStatusCode' => $response->getStatusCode()]
                    );
                    return false;
                }

                ($loggerProxy = $logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
                && $loggerProxy->log('HTTP server status is OK');
                return true;
            }
        );

        if (!$checkResult) {
            if (is_null($lastException)) {
                ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
                && $loggerProxy->log('Failed to send request to check HTTP server status');
            } else {
                ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
                && $loggerProxy->logThrowable($lastException, 'Failed to send request to check HTTP server status');
            }
        }

        return $checkResult;
    }

    /**
     * @param bool $keepElasticApmEnvVars
     *
     * @return array<string, string>
     */
    protected static function inheritedEnvVars(bool $keepElasticApmEnvVars): array
    {
        if ($keepElasticApmEnvVars) {
            return getenv();
        }

        return array_filter(
            getenv(),
            function (string $envVarName): bool {
                return
                    TextUtil::isPrefixOf(
                        TestConfigUtil::ENV_VAR_NAME_PREFIX,
                        $envVarName,
                        false /* <- isCaseSensitive */
                    )
                    || TextUtil::isPrefixOf(
                        EnvVarsRawSnapshotSource::DEFAULT_NAME_PREFIX . 'LOG_',
                        $envVarName,
                        false /* <- isCaseSensitive */
                    )
                    || !TextUtil::isPrefixOf(
                        EnvVarsRawSnapshotSource::DEFAULT_NAME_PREFIX,
                        $envVarName,
                        false /* <- isCaseSensitive */
                    );
            },
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * @param int|null              $port
     * @param string|null           $serverId
     * @param string                $dbgServerDesc
     * @param Closure               $cmdLineGenFunc
     * @param bool                  $keepElasticApmEnvVars
     * @param array<string, string> $additionalEnvVars
     *
     * @phpstan-param   Closure(int $port): string $cmdLineGenFunc
     */
    protected function ensureHttpServerIsRunning(
        ?int &$port,
        ?string &$serverId,
        string $dbgServerDesc,
        Closure $cmdLineGenFunc,
        bool $keepElasticApmEnvVars,
        array $additionalEnvVars = []
    ): void {
        if (!is_null($port)) {
            TestCase::assertNotNull($serverId);
            return;
        }
        TestCase::assertNull($serverId);

        /** @var int|null */
        $currentTryPort = null;
        for ($tryCount = 0; $tryCount < self::MAX_TRIES_TO_START_SERVER; ++$tryCount) {
            $currentTryPort = $this->findFreePortToListen();
            $currentTryServerId = $this->generateSecondaryIdFromTestEnvId();
            $cmdLine = $cmdLineGenFunc($currentTryPort);

            $logger = $this->logger->inherit()->addAllContext(
                [
                    'tryCount'           => $tryCount,
                    'maxTries'           => self::MAX_TRIES_TO_START_SERVER,
                    'dbgServerDesc'      => $dbgServerDesc,
                    'currentTryPort'     => $currentTryPort,
                    'currentTryServerId' => $currentTryServerId,
                    'cmdLine'            => $cmdLine,
                ]
            );

            ($loggerProxy = $logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log('Starting HTTP server...');

            $sharedDataPerProcessOptName = AllComponentTestsOptionsMetadata::SHARED_DATA_PER_PROCESS_OPTION_NAME;
            $sharedDataPerProcess = $this->buildSharedDataPerProcess($currentTryServerId, $currentTryPort);
            TestProcessUtil::startBackgroundProcess(
                $cmdLine,
                self::inheritedEnvVars($keepElasticApmEnvVars)
                + [
                    TestConfigUtil::envVarNameForTestOption($sharedDataPerProcessOptName) =>
                        SerializationUtil::serializeAsJson($sharedDataPerProcess),
                ]
                + $additionalEnvVars
            );

            if (self::isHttpServerRunning($currentTryPort, $currentTryServerId, $dbgServerDesc)) {
                ($loggerProxy = $logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
                && $loggerProxy->log('Started HTTP server');
                $port = $currentTryPort;
                $serverId = $currentTryServerId;
                return;
            }

            ($loggerProxy = $logger->ifNoticeLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log('Failed to start HTTP server');
        }

        throw new RuntimeException("Failed to start HTTP server. dbgServerDesc: $dbgServerDesc.");
    }

    private static function runScriptNameToCmdLine(string $runScriptName): string
    {
        return 'php ' . '"' . __DIR__ . DIRECTORY_SEPARATOR . $runScriptName . '"';
    }

    private function ensureAuxHttpServerIsRunning(
        string $dbgServerDesc,
        string $runScriptName,
        ?int &$port,
        ?string &$serverId
    ): void {
        $this->ensureHttpServerIsRunning(
            $port /* <- ref */,
            $serverId /* <- ref */,
            $dbgServerDesc,
            /* cmdLineGenFunc: */
            function (/** @noinspection PhpUnusedParameterInspection */ int $port) use ($runScriptName) {
                return self::runScriptNameToCmdLine($runScriptName);
            },
            true /* $keepElasticApmEnvVars */
        );
    }

    private function ensureResourcesCleanerRunning(): void
    {
        $this->ensureAuxHttpServerIsRunning(
            ClassNameUtil::fqToShort(ResourcesCleaner::class) /* <- dbgServerDesc */,
            'runResourcesCleaner.php' /* <- runScriptName */,
            $this->resourcesCleanerPort /* <- ref */,
            $this->resourcesCleanerServerId /* <- ref */
        );
    }

    protected function ensureMockApmServerRunning(): void
    {
        $this->ensureResourcesCleanerRunning();

        $this->ensureAuxHttpServerIsRunning(
            ClassNameUtil::fqToShort(MockApmServer::class) /* <- dbgServerDesc */,
            'runMockApmServer.php' /* <- runScriptName */,
            $this->mockApmServerPort /* <- ref */,
            $this->mockApmServerId /* <- ref */
        );
    }

    protected function buildSharedDataPerProcess(
        ?string $targetProcessServerId = null,
        ?int $targetProcessPort = null
    ): SharedDataPerProcess {
        $result = new SharedDataPerProcess();

        $currentProcessId = getmypid();
        if ($currentProcessId === false) {
            throw new RuntimeException('Failed to get current process ID');
        }
        $result->rootProcessId = $currentProcessId;

        $result->resourcesCleanerServerId = $this->resourcesCleanerServerId;
        $result->resourcesCleanerPort = $this->resourcesCleanerPort;

        $result->thisServerId = $targetProcessServerId;
        $result->thisServerPort = $targetProcessPort;

        return $result;
    }

    /**
     * @param array<string, string> $additionalEnvVars
     *
     * @return array<string, string>
     */
    protected function buildEnvVars(array $additionalEnvVars): array
    {
        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Entered', ['additionalEnvVars' => $additionalEnvVars]);

        /** @var array<string, string> */
        $result = getenv();

        $result += $additionalEnvVars;

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
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

            $this->ensureMockApmServerRunning();
            TestCase::assertNotNull($this->mockApmServerPort);
            $testProperties->agentConfigSetter->set(
                OptionNames::SERVER_URL,
                'http://localhost:' . $this->mockApmServerPort
            );

            TestCase::assertTrue(!isset($testProperties->sharedDataPerRequest->agentEphemeralId));
            $testProperties->sharedDataPerRequest->agentEphemeralId = $this->generateSecondaryIdFromTestEnvId();
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

        $this->signalResourcesCleanerToExit();
    }

    private function signalResourcesCleanerToExit(): void
    {
        if (is_null($this->resourcesCleanerPort)) {
            return;
        }
        TestCase::assertNotNull($this->resourcesCleanerServerId);

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Signaling ' . ClassNameUtil::fqToShort(ResourcesCleaner::class) . ' to clean and exit...'
        );

        try {
            TestHttpClientUtil::sendHttpRequest(
                $this->resourcesCleanerPort,
                HttpConsts::METHOD_POST,
                ResourcesCleaner::CLEAN_AND_EXIT_URI_PATH,
                SharedDataPerRequest::fromServerId($this->resourcesCleanerServerId)
            );
        } catch (GuzzleException $ex) {
            // clean-and-exit request is expected to throw
            // because ResourcesCleaner process exits before responding
        }

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Signaled ' . ClassNameUtil::fqToShort(ResourcesCleaner::class) . ' to clean and exit'
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
        /** @var Exception|null */
        $lastException = null;
        $lastCheckedNextIntakeApiRequestIndex = $this->dataFromAgent->nextIntakeApiRequestIndexToFetch();
        $numberOfFailedAttempts = 0;
        $numberOfAttempts = 0;
        $hasPassed = (new PollingCheck(
            __FUNCTION__ . ' passes',
            3 * self::DATA_FROM_AGENT_MAX_WAIT_TIME_SECONDS * 1000 * 1000 /* maxWaitTimeInMicroseconds */,
            AmbientContext::loggerFactory()
        ))->run(
            function () use (
                $timeBeforeRequestToApp,
                $testProperties,
                $verifyFunc,
                &$lastException,
                &$lastCheckedNextIntakeApiRequestIndex,
                &$numberOfAttempts,
                &$numberOfFailedAttempts
            ) {
                ++$numberOfAttempts;
                try {
                    $lastCheckedIndexBeforeUpdate = $lastCheckedNextIntakeApiRequestIndex;
                    $this->ensureLatestDataFromMockApmServer($timeBeforeRequestToApp);
                    $lastCheckedNextIntakeApiRequestIndex = $this->dataFromAgent->nextIntakeApiRequestIndexToFetch();
                    if (
                        !is_null($lastException)
                        && ($lastCheckedIndexBeforeUpdate === $lastCheckedNextIntakeApiRequestIndex)
                    ) {
                        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
                        && $loggerProxy->log(
                            'No new data since the last check - there is no point in invoking $verifyFunc() again',
                            [
                                'lastCheckedIndexBeforeUpdate'         => $lastCheckedIndexBeforeUpdate,
                                'lastCheckedNextIntakeApiRequestIndex' => $lastCheckedNextIntakeApiRequestIndex,
                            ]
                        );
                        return false;
                    }

                    $this->verifyDataAgainstRequest($testProperties);

                    ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
                    && $loggerProxy->log('Calling $verifyFunc supplied by the text case...');

                    $verifyFunc($this->dataFromAgent);
                } catch (Exception $ex) {
                    ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
                    && $loggerProxy->logThrowable($ex, "Attempt $numberOfAttempts failed");

                    if ($ex instanceof ConnectException || $ex instanceof PhpUnitException) {
                        $lastException = $ex;
                        ++$numberOfFailedAttempts;
                        return false;
                    }

                    /** @noinspection PhpUnhandledExceptionInspection */
                    throw $ex;
                }
                return true;
            }
        );

        if (!$hasPassed) {
            TestCase::assertNotNull($lastException);

            ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                __FUNCTION__ . ' failed',
                [
                    'last exception from verifyFunc()'     => $lastException,
                    'numberOfAttempts'                     => $numberOfAttempts,
                    'numberOfFailedAttempts'               => $numberOfFailedAttempts,
                    'timeBeforeRequestToApp'               => $timeBeforeRequestToApp,
                    'testProperties'                       => $testProperties,
                    'this'                                 => $this,
                    'lastCheckedNextIntakeApiRequestIndex' => $lastCheckedNextIntakeApiRequestIndex,
                ]
            );

            throw $lastException;
        }
    }

    private function ensureLatestDataFromMockApmServer(float $timeBeforeRequestToApp): void
    {
        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Starting...');

        try {
            $newIntakeApiRequests = $this->fetchLatestDataFromMockApmServer();
            if (!empty($newIntakeApiRequests)) {
                $this->dataFromAgent->addIntakeApiRequests($newIntakeApiRequests, $timeBeforeRequestToApp);
            }

            ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log('Done');
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
        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Verifying data received from the agent...');

        self::verifyAgentEphemeralId($testProperties, $this->dataFromAgent);

        $this->verifyHttpRequestHeaders($testProperties);

        $this->verifyMetadata($testProperties);

        $rootTransaction = TestCaseBase::findRootTransaction($this->dataFromAgent->idToTransaction());
        $this->verifyRootTransactionName($testProperties, $rootTransaction);
        $this->verifyRootTransactionType($testProperties, $rootTransaction);

        TestCaseBase::assertValidTransactionsAndSpans(
            $this->dataFromAgent->idToTransaction(),
            $this->dataFromAgent->idToSpan()
        );
    }

    protected function verifyHttpRequestHeaders(TestProperties $testProperties): void
    {
        $configuredApiKey = $testProperties->getConfiguredAgentOption(OptionNames::API_KEY);

        $this->verifyAuthHttpRequestHeaders(
        /* expectedApiKey: */
            $configuredApiKey,
            /* expectedSecretToken: */
            is_null($configuredApiKey) ? $testProperties->getConfiguredAgentOption(OptionNames::SECRET_TOKEN) : null,
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
            ? (is_null($expectedSecretToken) ? null : "Bearer $expectedSecretToken")
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
        self::verifyEnvironment(
            Tracer::limitNullableKeywordString($testProperties->getConfiguredAgentOption(OptionNames::ENVIRONMENT)),
            $this->dataFromAgent
        );

        $configuredServiceName = $testProperties->getConfiguredAgentOption(OptionNames::SERVICE_NAME);
        $expectedServiceName = is_null($configuredServiceName)
            ? MetadataDiscoverer::DEFAULT_SERVICE_NAME
            : MetadataDiscoverer::adaptServiceName($configuredServiceName);
        self::verifyServiceName($expectedServiceName, $this->dataFromAgent);

        $expectedServiceVersion = Tracer::limitNullableKeywordString(
            $testProperties->getConfiguredAgentOption(OptionNames::SERVICE_VERSION)
        );
        self::verifyServiceVersion($expectedServiceVersion, $this->dataFromAgent);
    }

    public static function verifyAgentEphemeralId(TestProperties $testProperties, DataFromAgent $dataFromAgent): void
    {
        foreach ($dataFromAgent->metadata() as $metadata) {
            TestCase::assertTrue(isset($metadata->service->agent));
            TestCase::assertSame(
                $testProperties->sharedDataPerRequest->agentEphemeralId,
                $metadata->service->agent->ephemeralId
            );
        }
    }

    public static function verifyEnvironment(?string $expected, DataFromAgent $dataFromAgent): void
    {
        foreach ($dataFromAgent->metadata() as $metadata) {
            TestCase::assertSame($expected, $metadata->service->environment);
        }
    }

    public static function verifyServiceName(string $expected, DataFromAgent $dataFromAgent): void
    {
        foreach ($dataFromAgent->metadata() as $metadata) {
            TestCase::assertSame($expected, $metadata->service->name);
        }
    }

    public static function verifyServiceVersion(?string $expected, DataFromAgent $dataFromAgent): void
    {
        foreach ($dataFromAgent->metadata() as $metadata) {
            TestCase::assertSame($expected, $metadata->service->version);
        }
    }

    protected function verifyRootTransactionName(
        TestProperties $testProperties,
        TransactionData $rootTransaction
    ): void {
        if (!is_null($testProperties->expectedTransactionName)) {
            TestCase::assertSame($testProperties->expectedTransactionName, $rootTransaction->name);
        }
    }

    protected function verifyRootTransactionType(
        TestProperties $testProperties,
        TransactionData $rootTransaction
    ): void {
        if (!is_null($testProperties->transactionType)) {
            TestCase::assertSame($testProperties->transactionType, $rootTransaction->type);
        }
    }

    /**
     * @return IntakeApiRequest[]
     */
    private function fetchLatestDataFromMockApmServer(): array
    {
        TestCase::assertNotNull($this->mockApmServerPort);
        TestCase::assertNotNull($this->mockApmServerId);

        $response = TestHttpClientUtil::sendHttpRequest(
            $this->mockApmServerPort,
            HttpConsts::METHOD_GET,
            MockApmServer::MOCK_API_URI_PREFIX . MockApmServer::GET_INTAKE_API_REQUESTS,
            SharedDataPerRequest::fromServerId($this->mockApmServerId),
            [MockApmServer::FROM_INDEX_HEADER_NAME => strval($this->dataFromAgent->nextIntakeApiRequestIndexToFetch())]
        );

        if ($response->getStatusCode() !== HttpConsts::STATUS_OK) {
            throw new RuntimeException('Received unexpected status code');
        }

        $decodedBody = JsonUtil::decode($response->getBody()->getContents(), /* asAssocArray */ true);

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

    abstract public function isHttp(): bool;
}
