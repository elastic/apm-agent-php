<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests\Util;

use Closure;
use Elastic\Apm\Impl\Log\Level;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Util\DbgUtil;
use Elastic\Apm\Impl\Util\ObjectToStringUsingPropertiesTrait;
use Elastic\Apm\Tests\Util\LogCategoryForTests;
use RuntimeException;
use Throwable;

abstract class CliProcessBase
{
    use ObjectToStringUsingPropertiesTrait;

    /** @var Logger */
    private $logger;

    protected function __construct()
    {
        $this->logger = self::buildLogger()->addContext('this', $this);


        ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Done',
            [
                'AmbientContext::testConfig()' => AmbientContext::testConfig(),
                'Environment variables' => getenv(),
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
                strval(AmbientContext::testConfig())
            );
            TestAssertUtil::assertThat(
                !is_null(AmbientContext::testConfig()->sharedDataPerProcess->resourcesCleanerPort),
                strval(AmbientContext::testConfig())
            );
        }
    }

    /**
     * @param Closure $runImpl
     *
     * @throws Throwable
     *
     * @phpstan-param Closure(CliProcessBase): void $runImpl
     */
    protected static function runSkeleton(Closure $runImpl): void
    {
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
            && $loggerProxy->log(
                'Throwable escaped to the top of the script',
                ['throwable' => $throwableToLog]
            );
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
                'Required configuration option ' . $optName . " (environment variable $envVarName)" . ' is not set.'
                . ' AmbientContext::dbgProcessName(): ' . AmbientContext::dbgProcessName() . '.'
                . ' AmbientContext::config(): ' . AmbientContext::testConfig() . '.'
            );
        }

        return $optValue;
    }

    protected static function verifyRequiredSharedDataPropertyIsSet(
        SharedDataBase $sharedData,
        string $propName
    ): void {
        if (is_null($sharedData->$propName)) {
            throw new RuntimeException(
                'Required shared data property is not set'
                . '. sharedData: ' . $sharedData
                . '. $propName: ' . $propName
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

        assert(!is_null(AmbientContext::testConfig()->sharedDataPerProcess->resourcesCleanerPort));
        assert(!is_null(AmbientContext::testConfig()->sharedDataPerProcess->resourcesCleanerServerId));
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
