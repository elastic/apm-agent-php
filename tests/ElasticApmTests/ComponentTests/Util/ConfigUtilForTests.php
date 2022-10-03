<?php

/*
 * Licensed to Elasticsearch B.V. under one or more contributor
 * license agreements. See the NOTICE file distributed with
 * this work for additional information regarding copyright
 * ownership. Elasticsearch B.V. licenses this file to you under
 * the Apache License, Version 2.0 (the "License"); you may
 * not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations
 * under the License.
 */

declare(strict_types=1);

namespace ElasticApmTests\ComponentTests\Util;

use Elastic\Apm\Impl\Config\CompositeRawSnapshotSource;
use Elastic\Apm\Impl\Config\EnvVarsRawSnapshotSource;
use Elastic\Apm\Impl\Config\OptionNames;
use Elastic\Apm\Impl\Config\Parser;
use Elastic\Apm\Impl\Config\RawSnapshotSourceInterface;
use Elastic\Apm\Impl\GlobalTracerHolder;
use Elastic\Apm\Impl\Log\LoggerFactory;
use Elastic\Apm\Impl\Util\StaticClassTrait;
use RuntimeException;

final class ConfigUtilForTests
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
        ?RawSnapshotSourceInterface $additionalConfigSource,
        LoggerFactory $loggerFactory
    ): ConfigSnapshotForTests {
        $envVarConfigSource = new EnvVarsRawSnapshotSource(ConfigUtilForTests::ENV_VAR_NAME_PREFIX);
        $configSource =  $additionalConfigSource === null
            ? $envVarConfigSource
            : new CompositeRawSnapshotSource(
                [
                    $additionalConfigSource,
                    $envVarConfigSource,
                ]
            );
        $parser = new Parser($loggerFactory);
        $allOptsMeta = AllComponentTestsOptionsMetadata::get();
        $optNameToParsedValue = $parser->parse($allOptsMeta, $configSource->currentSnapshot($allOptsMeta));
        return new ConfigSnapshotForTests($optNameToParsedValue);
    }

    public static function assertAgentDisabled(): void
    {
        $envVarName = ConfigUtilForTests::envVarNameForAgentOption(OptionNames::ENABLED);
        $envVarValue = getenv($envVarName);
        if ($envVarValue !== 'false') {
            throw new RuntimeException(
                "Environment variable $envVarName should be set to `false'."
                . ' Instead it is ' . ($envVarValue === false ? 'not set' : "set to `$envVarValue'") . '.'
            );
        }

        if (GlobalTracerHolder::getValue()->isRecording()) {
            throw new RuntimeException('Tracer should not be recording component tests auxiliary processes');
        }
    }
}
