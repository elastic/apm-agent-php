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
use Elastic\Apm\Impl\Config\Parser;
use Elastic\Apm\Impl\Config\RawSnapshotSourceInterface;
use Elastic\Apm\Impl\Log\Backend as LogBackend;
use Elastic\Apm\Impl\Log\Level as LogLevel;
use Elastic\Apm\Impl\Log\LoggerFactory;
use Elastic\Apm\Impl\Util\StaticClassTrait;
use ElasticApmTests\Util\LogSinkForTests;

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
