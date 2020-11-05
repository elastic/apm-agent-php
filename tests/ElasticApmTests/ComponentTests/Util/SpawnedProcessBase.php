<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace ElasticApmTests\ComponentTests\Util;

use Closure;
use Elastic\Apm\Impl\Log\Level;
use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableToString;
use Elastic\Apm\Impl\Log\LoggableTrait;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Log\LoggingSubsystem;
use Elastic\Apm\Impl\Util\DbgUtil;
use Elastic\Apm\Impl\Util\ExceptionUtil;
use ElasticApmTests\Util\LogCategoryForTests;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

abstract class SpawnedProcessBase implements LoggableInterface
{
    use LoggableTrait;

    /** @var Logger */
    private $logger;

    protected function __construct()
    {
        $this->logger = self::buildLogger()->addContext('this', $this);


        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Done',
            [
                'AmbientContext::testConfig()' => AmbientContext::testConfig(),
                'Environment variables'        => getenv(),
            ]
        );
    }

    private static function buildLogger(): Logger
    {
        return AmbientContext::loggerFactory()->loggerForClass(
            LogCategoryForTests::TEST_UTIL,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        );
    }

    protected function processConfig(): void
    {
        self::getRequiredTestOption(AllComponentTestsOptionsMetadata::SHARED_DATA_PER_PROCESS_OPTION_NAME);
        if ($this->shouldRegisterThisProcessWithResourcesCleaner()) {
            TestAssertUtil::assertThat(
                !is_null(AmbientContext::testConfig()->sharedDataPerProcess->resourcesCleanerServerId),
                LoggableToString::convert(AmbientContext::testConfig())
            );
            TestAssertUtil::assertThat(
                !is_null(AmbientContext::testConfig()->sharedDataPerProcess->resourcesCleanerPort),
                LoggableToString::convert(AmbientContext::testConfig())
            );
        }
    }

    /**
     * @param Closure $runImpl
     *
     * @throws Throwable
     *
     * @phpstan-param Closure(SpawnedProcessBase): void $runImpl
     */
    protected static function runSkeleton(Closure $runImpl): void
    {
        LoggingSubsystem::$isInTestingContext = true;

        try {
            AmbientContext::init(/* dbgProcessName */ DbgUtil::fqToShortClassName(get_called_class()));
            $thisObj = new static(); // @phpstan-ignore-line
            $thisObj->processConfig();

            if ($thisObj->shouldRegisterThisProcessWithResourcesCleaner()) {
                $thisObj->registerWithResourcesCleaner();
            }

            $runImpl($thisObj);
        } catch (Throwable $throwable) {
            $level = Level::CRITICAL;
            $throwableToLog = $throwable;
            if ($throwable instanceof WrappedAppCodeException) {
                $level = Level::NOTICE;
                $throwableToLog = $throwable->wrappedException();
            }
            $logger = isset($thisObj) ? $thisObj->logger : self::buildLogger();
            ($loggerProxy = $logger->ifLevelEnabled($level, __LINE__, __FUNCTION__))
            && $loggerProxy->logThrowable($throwableToLog, 'Throwable escaped to the top of the script');
            /** @noinspection PhpUnhandledExceptionInspection */
            throw $throwableToLog;
        }
    }

    /**
     * @param string $optName
     *
     * @return mixed
     */
    protected static function getRequiredTestOption(string $optName)
    {
        $optValue = AmbientContext::testConfig()->getOptionValueByName($optName);
        if (is_null($optValue)) {
            $envVarName = TestConfigUtil::envVarNameForTestOption($optName);
            throw new RuntimeException(
                ExceptionUtil::buildMessage(
                    'Required configuration option is not set',
                    [
                        'optName'        => $optName,
                        'envVarName'     => $envVarName,
                        'dbgProcessName' => AmbientContext::dbgProcessName(),
                        'testConfig'     => AmbientContext::testConfig(),
                    ]
                )
            );
        }

        return $optValue;
    }

    protected static function verifyRequiredSharedDataPropertyIsSet(
        SharedData $sharedData,
        string $propName
    ): void {
        if (is_null($sharedData->$propName)) {
            throw new RuntimeException(
                ExceptionUtil::buildMessage(
                    'Required shared data property is not set',
                    ['sharedData' => $sharedData, '. $propName' => $propName]
                )
            );
        }
    }

    protected function shouldRegisterThisProcessWithResourcesCleaner(): bool
    {
        return true;
    }

    protected function registerWithResourcesCleaner(): void
    {
        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Registering with ' . DbgUtil::fqToShortClassName(ResourcesCleaner::class) . '...'
        );

        TestCase::assertNotNull(AmbientContext::testConfig()->sharedDataPerProcess->resourcesCleanerPort);
        TestCase::assertNotNull(AmbientContext::testConfig()->sharedDataPerProcess->resourcesCleanerServerId);
        $response = TestHttpClientUtil::sendHttpRequest(
            AmbientContext::testConfig()->sharedDataPerProcess->resourcesCleanerPort,
            HttpConsts::METHOD_POST,
            ResourcesCleaner::REGISTER_PROCESS_TO_TERMINATE_URI_PATH,
            SharedDataPerRequest::fromServerId(
                AmbientContext::testConfig()->sharedDataPerProcess->resourcesCleanerServerId
            ),
            [ResourcesCleaner::PID_QUERY_HEADER_NAME => strval(getmypid())]
        );
        if ($response->getStatusCode() !== HttpConsts::STATUS_OK) {
            throw new RuntimeException(
                'Failed to register with '
                . DbgUtil::fqToShortClassName(ResourcesCleaner::class)
            );
        }

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Successfully registered with ' . DbgUtil::fqToShortClassName(ResourcesCleaner::class)
        );
    }
}
