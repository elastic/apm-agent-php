<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests\Util;

use Elastic\Apm\Impl\Config\CompositeRawSnapshotSource;
use Elastic\Apm\Impl\Config\EnvVarsRawSnapshotSource;
use Elastic\Apm\Impl\Config\Parser;
use Elastic\Apm\Impl\Config\RawSnapshotSourceInterface;
use Elastic\Apm\Impl\Log\Backend as LogBackend;
use Elastic\Apm\Impl\Log\Level as LogLevel;
use Elastic\Apm\Impl\Log\LoggerFactory;
use Elastic\Apm\Impl\Util\StaticClassTrait;
use Elastic\Apm\Tests\Util\LogSinkForTests;

final class TestConfigUtil
{
    use StaticClassTrait;

    public const ENV_VAR_NAME_PREFIX = 'ELASTIC_APM_PHP_TESTS_';

    public static function envVarNameForAgentOption(string $optName): string
    {
        return EnvVarsRawSnapshotSource::optionNameToEnvVarName(
            EnvVarsRawSnapshotSource::DEFAULT_NAME_PREFIX,
            $optName
        );
    }

    public static function envVarNameForTestOption(string $optName): string
    {
        return EnvVarsRawSnapshotSource::optionNameToEnvVarName(self::ENV_VAR_NAME_PREFIX, $optName);
    }

    public static function read(
        string $dbgProcessName,
        ?RawSnapshotSourceInterface $additionalConfigSource
    ): TestConfigSnapshot {
        $envVarConfigSource = new EnvVarsRawSnapshotSource(TestConfigUtil::ENV_VAR_NAME_PREFIX);
        $configSource = is_null($additionalConfigSource)
            ? $envVarConfigSource
            : new CompositeRawSnapshotSource(
                [
                    $additionalConfigSource,
                    $envVarConfigSource,
                ]
            );
        $parser = new Parser(
            new LoggerFactory(new LogBackend(LogLevel::ERROR, new LogSinkForTests($dbgProcessName)))
        );
        $allOptsMeta = AllComponentTestsOptionsMetadata::build();
        return new TestConfigSnapshot(
            $parser->parse($allOptsMeta, $configSource->currentSnapshot($allOptsMeta))
        );
    }
}
