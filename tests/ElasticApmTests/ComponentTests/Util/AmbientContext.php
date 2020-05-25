<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests\Util;

use Elastic\Apm\Impl\Config\EnvVarsRawSnapshotSource;
use Elastic\Apm\Impl\Config\Parser;
use Elastic\Apm\Impl\Log\Backend as LogBackend;
use Elastic\Apm\Impl\Log\Level as LogLevel;
use Elastic\Apm\Impl\Log\LoggerFactory;
use Elastic\Apm\Tests\Util\TestLogCategory;
use Elastic\Apm\Tests\Util\TestLogSink;
use RuntimeException;

final class AmbientContext
{
    public const ENV_VAR_NAME_PREFIX = 'ELASTIC_APM_TESTS_';

    /** @var self */
    private static $singletonInstance;

    /** @var LoggerFactory */
    private $loggerFactory;

    /** @var EnvVarsRawSnapshotSource */
    private $envVarConfigSource;

    /** @var ConfigSnapshot */
    private $config;

    private function __construct(string $dbgProcessName)
    {
        $logPrefix = $dbgProcessName . ' [PID: ' . getmypid() . '] ';
        $allOptsMeta = AllComponentTestsOptionsMetadata::build();
        $this->envVarConfigSource = new EnvVarsRawSnapshotSource(self::ENV_VAR_NAME_PREFIX, array_keys($allOptsMeta));
        $parser = new Parser($allOptsMeta, self::createLoggerFactory(LogLevel::ERROR, $logPrefix));
        $this->config = new ConfigSnapshot($parser->parse($this->envVarConfigSource->currentSnapshot()));

        $this->loggerFactory = self::createLoggerFactory($this->config->logLevel(), $logPrefix);
    }

    public static function init(string $dbgProcessName): void
    {
        if (!isset(self::$singletonInstance)) {
            self::$singletonInstance = new AmbientContext($dbgProcessName);
        }

        if (AmbientContext::config()->appCodeHostKind() === AppCodeHostKind::NOT_SET) {
            $envVarName = EnvVarsRawSnapshotSource::optionNameToEnvVarName(
                AmbientContext::ENV_VAR_NAME_PREFIX,
                AppCodeHostKindOptionMetadata::NAME
            );
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

    private static function createLoggerFactory(int $logLevel, string $logPrefix): LoggerFactory
    {
        return new LoggerFactory(new LogBackend($logLevel, new TestLogSink($logPrefix)));
    }
}
