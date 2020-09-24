<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests\Util;

use Elastic\Apm\Impl\Log\Level;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Util\ArrayUtil;
use Elastic\Apm\Impl\Util\DbgUtil;
use Elastic\Apm\Impl\Util\ObjectToStringBuilder;
use Elastic\Apm\Tests\Util\TestLogCategory;
use RuntimeException;
use Throwable;

abstract class CliProcessBase
{
    /** @var string */
    protected $runScriptFile;

    /** @var Logger */
    private $logger;

    protected function __construct(string $runScriptFile)
    {
        $this->runScriptFile = $runScriptFile;
        $this->logger = self::buildLogger()->addContext('this', $this);
    }

    private static function buildLogger(): Logger
    {
        return AmbientContext::loggerFactory()->loggerForClass(
            TestLogCategory::TEST_UTIL,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        );
    }

    protected function processConfig(): void
    {
        if ($this->shouldRegisterThisProcessWithResourcesCleaner()) {
            self::getRequiredTestOption(AllComponentTestsOptionsMetadata::RESOURCES_CLEANER_PORT_OPTION_NAME);
            self::getRequiredTestOption(
                AllComponentTestsOptionsMetadata::RESOURCES_CLEANER_SERVER_ID_OPTION_NAME
            );
        }
    }

    abstract protected function runImpl(): void;

    public static function run(string $runScriptFile): void
    {
        try {
            AmbientContext::init(/* dbgProcessName */ DbgUtil::fqToShortClassName(get_called_class()));
            $thisObj = new static($runScriptFile); // @phpstan-ignore-line
            $thisObj->processConfig();

            if ($thisObj->shouldRegisterThisProcessWithResourcesCleaner()) {
                $thisObj->registerWithResourcesCleaner();
            }

            $thisObj->runImpl();
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
        $optValue = AmbientContext::config()->getOptionValueByName($optName);
        if (is_null($optValue)) {
            $envVarName = TestConfigUtil::envVarNameForTestsOption($optName);
            throw new RuntimeException(
                'Required configuration option ' . $optName . " (environment variable $envVarName)" . ' is not set'
            );
        }

        return $optValue;
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

        $response = TestHttpClientUtil::sendHttpRequest(
            AmbientContext::config()->resourcesCleanerPort(),
            AmbientContext::config()->resourcesCleanerServerId(),
            HttpConsts::METHOD_POST,
            ResourcesCleaner::REGISTER_PROCESS_TO_TERMINATE_URI_PATH,
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

    public function __toString(): string
    {
        $builder = new ObjectToStringBuilder(DbgUtil::fqToShortClassName(get_called_class()));
        $this->toStringAddProperties($builder);
        return $builder->build();
    }

    protected function toStringAddProperties(ObjectToStringBuilder $builder): void
    {
        $builder->add('config', AmbientContext::config());
    }
}
