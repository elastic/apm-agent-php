<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests\Util;

use Elastic\Apm\Impl\Config\EnvVarsRawSnapshotSource;
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

    private function verifyConfig(): void
    {
        if (empty(AmbientContext::config()->testEnvId())) {
            self::throwRequiredConfigOptionIsSet(AllComponentTestsOptionsMetadata::TEST_ENV_ID_OPTION_NAME);
        }

        if (
            $this->shouldRegisterWithSpawnedProcessesCleaner()
            && AmbientContext::config()->spawnedProcessesCleanerPort()
               === AllComponentTestsOptionsMetadata::INT_OPTION_NOT_SET
        ) {
            self::throwRequiredConfigOptionIsSet(
                AllComponentTestsOptionsMetadata::SPAWNED_PROCESSES_CLEANER_PORT_OPTION_NAME
            );
        }
    }

    abstract protected function parseArgs(): void;

    abstract protected function cliHelpOptions(): string;

    abstract protected function runImpl(): void;

    public static function run(string $runScriptFile): void
    {
        try {
            AmbientContext::init(/* dbgProcessName */ DbgUtil::fqToShortClassName(get_called_class()));
            $thisObj = new static($runScriptFile); // @phpstan-ignore-line
            $thisObj->parseArgs();
            $thisObj->verifyConfig();

            if ($thisObj->shouldRegisterWithSpawnedProcessesCleaner()) {
                $thisObj->registerWithSpawnedProcessesCleaner();
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

    private static function throwRequiredConfigOptionIsSet(string $optName): void
    {
        $envVarName = EnvVarsRawSnapshotSource::optionNameToEnvVarName(AmbientContext::ENV_VAR_NAME_PREFIX, $optName);
        throw new RuntimeException(
            'Required configuration option ' . $optName . " (environment variable $envVarName)" . ' is not set'
        );
    }

    protected function shouldRegisterWithSpawnedProcessesCleaner(): bool
    {
        return true;
    }

    protected function registerWithSpawnedProcessesCleaner(): void
    {
        assert(
            AmbientContext::config()->spawnedProcessesCleanerPort()
            !== AllComponentTestsOptionsMetadata::INT_OPTION_NOT_SET
        );

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Registering with ' . DbgUtil::fqToShortClassName(SpawnedProcessesCleaner::class) . '...'
        );

        SpawnedProcessesCleaner::sendRequestToRegisterProcess(
            AmbientContext::config()->spawnedProcessesCleanerPort(),
            AmbientContext::config()->testEnvId(),
            getmypid()
        );

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Successfully registered with ' . DbgUtil::fqToShortClassName(SpawnedProcessesCleaner::class)
        );
    }

    /**
     * @param string               $cliOptName
     * @param array<string, mixed> $parsedCliOptions
     *
     * @return string
     */
    protected function checkRequiredCliOption(string $cliOptName, array $parsedCliOptions): string
    {
        $cliOptValue = ArrayUtil::getValueIfKeyExistsElse($cliOptName, $parsedCliOptions, null);
        if (is_null($cliOptValue)) {
            ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                "Invalid command line: required --$cliOptName is missing."
                . ' Expected command line format:' . PHP_EOL
                . $this->runScriptFile . ' ' . $this->cliHelpOptions()
            );
            exit(1);
        }
        return $cliOptValue;
    }

    public function __toString(): string
    {
        $builder = new ObjectToStringBuilder(DbgUtil::fqToShortClassName(get_called_class()));
        $this->toStringAddProperties($builder);
        return $builder->build();
    }

    protected function toStringAddProperties(ObjectToStringBuilder $builder): void
    {
        $builder->add('testEnvId', AmbientContext::config()->testEnvId());
        if ($this->shouldRegisterWithSpawnedProcessesCleaner()) {
            $builder->add('spawnedProcessesCleanerPort', AmbientContext::config()->spawnedProcessesCleanerPort());
        }
    }
}
