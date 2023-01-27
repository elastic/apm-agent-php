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

use Elastic\Apm\Impl\Config\BoolOptionMetadata;
use Elastic\Apm\Impl\Config\IntOptionMetadata;
use Elastic\Apm\Impl\Config\LogLevelOptionMetadata;
use Elastic\Apm\Impl\Config\NullableIntOptionMetadata;
use Elastic\Apm\Impl\Config\NullableStringOptionMetadata;
use Elastic\Apm\Impl\Config\NullableWildcardListOptionMetadata;
use Elastic\Apm\Impl\Config\OptionMetadata;
use Elastic\Apm\Impl\Log\Level as LogLevel;
use Elastic\Apm\Impl\Util\StaticClassTrait;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class AllComponentTestsOptionsMetadata
{
    use StaticClassTrait;

    public const APP_CODE_HOST_KIND_OPTION_NAME = 'app_code_host_kind';
    public const APP_CODE_PHP_INI_OPTION_NAME = 'app_code_php_ini';
    public const DATA_PER_PROCESS_OPTION_NAME = 'data_per_process';
    public const DATA_PER_REQUEST_OPTION_NAME = 'data_per_request';
    public const ESCALATED_RERUNS_MAX_COUNT_OPTION_NAME = 'escalated_reruns_max_count';
    public const LOG_LEVEL_OPTION_NAME = 'log_level';

    /** @var ?array<string, OptionMetadata<mixed>> */
    private static $vaLue = null;

    /**
     * @return array<string, OptionMetadata> Option name to metadata
     *
     * @phpstan-return array<string, OptionMetadata<mixed>> Option name to metadata
     */
    public static function get(): array
    {
        if (self::$vaLue !== null) {
            return self::$vaLue;
        }

        /** @var array<string, OptionMetadata<mixed>> $optNameToMeta */
        $optNameToMeta = [
            self::APP_CODE_HOST_KIND_OPTION_NAME => new NullableAppCodeHostKindOptionMetadata(),
            'app_code_php_exe' => new NullableStringOptionMetadata(),
            self::APP_CODE_PHP_INI_OPTION_NAME => new NullableStringOptionMetadata(),
            self::DATA_PER_PROCESS_OPTION_NAME => new NullableCustomOptionMetadata(
                function (string $rawValue): TestInfraDataPerProcess {
                    $deserializedObj = new TestInfraDataPerProcess();
                    $deserializedObj->deserializeFromString($rawValue);
                    return $deserializedObj;
                }
            ),
            self::DATA_PER_REQUEST_OPTION_NAME => new NullableCustomOptionMetadata(
                function (string $rawValue): TestInfraDataPerRequest {
                    $deserializedObj = new TestInfraDataPerRequest();
                    $deserializedObj->deserializeFromString($rawValue);
                    return $deserializedObj;
                }
            ),
            'delete_temp_php_ini'       => new BoolOptionMetadata(true),
            'env_vars_to_pass_through'  => new NullableWildcardListOptionMetadata(),
            self::ESCALATED_RERUNS_MAX_COUNT_OPTION_NAME
                                        => new IntOptionMetadata(/* min: */ 0, /* max: */ null, /* default: */ 10),
            'group'                     => new NullableStringOptionMetadata(),
            self::LOG_LEVEL_OPTION_NAME => new LogLevelOptionMetadata(LogLevel::INFO),
            'mysql_host'                => new NullableStringOptionMetadata(),
            'mysql_port'                => new NullableIntOptionMetadata(1, 65535),
            'mysql_user'                => new NullableStringOptionMetadata(),
            'mysql_password'            => new NullableStringOptionMetadata(),
            'mysql_db'                  => new NullableStringOptionMetadata(),
            'run_before_each_test'      => new NullableStringOptionMetadata(),
        ];

        self::$vaLue = $optNameToMeta;

        return self::$vaLue; // @phpstan-ignore-line
    }
}
