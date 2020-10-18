<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests\Util;

use Elastic\Apm\Impl\Config\EnvVarsRawSnapshotSource;
use Elastic\Apm\Impl\Config\Parser;
use Elastic\Apm\Impl\Log\Backend as LogBackend;
use Elastic\Apm\Impl\Log\Level as LogLevel;
use Elastic\Apm\Impl\Log\LoggerFactory;
use Elastic\Apm\Impl\Log\SinkInterface;
use Elastic\Apm\Tests\Util\TestLogSink;
use RuntimeException;

final class AmbientContext
{
    /** @var self */
    private static $singletonInstance;

    /** @var LoggerFactory */
    private $loggerFactory;

    /** @var EnvVarsRawSnapshotSource */
    private $envVarConfigSource;

    /** @var ConfigSnapshot */
    private $config;

    /** @var SinkInterface */
    private $logSink;

    private function __construct(string $dbgProcessName)
    {
        $allOptsMeta = AllComponentTestsOptionsMetadata::build();
        $this->logSink = new TestLogSink($dbgProcessName);
        $this->envVarConfigSource
            = new EnvVarsRawSnapshotSource(TestConfigUtil::ENV_VAR_NAME_PREFIX, array_keys($allOptsMeta));
        $parser = new Parser($allOptsMeta, self::createLoggerFactory(LogLevel::ERROR, $this->logSink));
        $this->config = new ConfigSnapshot($parser->parse($this->envVarConfigSource->currentSnapshot()));
        $this->loggerFactory = self::createLoggerFactory($this->config->logLevel(), $this->logSink);
    }

    public static function init(string $dbgProcessName): void
    {
        if (!isset(self::$singletonInstance)) {
            self::$singletonInstance = new AmbientContext($dbgProcessName);
        }

        if (self::config()->appCodeHostKind() === AppCodeHostKind::NOT_SET) {
            $envVarName = TestConfigUtil::envVarNameForTestsOption(AppCodeHostKindOptionMetadata::NAME);
            throw new RuntimeException(
                'Required configuration option ' . AppCodeHostKindOptionMetadata::NAME
                . " (environment variable $envVarName)" . ' is not set'
            );
        }
    }

    public static function config(): ConfigSnapshot
    {
        assert(isset(self::$singletonInstance));

        return self::$singletonInstance->config;
    }

    public static function envVarConfigSource(): EnvVarsRawSnapshotSource
    {
        assert(isset(self::$singletonInstance));

        return self::$singletonInstance->envVarConfigSource;
    }

    public static function loggerFactory(): LoggerFactory
    {
        assert(isset(self::$singletonInstance));

        return self::$singletonInstance->loggerFactory;
    }

    public static function logSink(): SinkInterface
    {
        assert(isset(self::$singletonInstance));

        return self::$singletonInstance->logSink;
    }

    private static function createLoggerFactory(int $logLevel, SinkInterface $logSink): LoggerFactory
    {
        return new LoggerFactory(new LogBackend($logLevel, $logSink));
    }
}
