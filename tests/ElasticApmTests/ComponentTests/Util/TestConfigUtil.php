<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests\Util;

use Elastic\Apm\Impl\Config\EnvVarsRawSnapshotSource;
use Elastic\Apm\Impl\Util\StaticClassTrait;

final class TestConfigUtil
{
    use StaticClassTrait;

    public const ENV_VAR_NAME_PREFIX = 'ELASTIC_APM_TESTS_';

    public static function envVarNameForOption(string $optName): string
    {
        return EnvVarsRawSnapshotSource::optionNameToEnvVarName(
            EnvVarsRawSnapshotSource::DEFAULT_NAME_PREFIX,
            $optName
        );
    }

    public static function envVarNameForTestsOption(string $optName): string
    {
        return EnvVarsRawSnapshotSource::optionNameToEnvVarName(self::ENV_VAR_NAME_PREFIX, $optName);
    }
}
