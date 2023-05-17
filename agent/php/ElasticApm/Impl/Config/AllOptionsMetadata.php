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

namespace Elastic\Apm\Impl\Config;

use Elastic\Apm\Impl\Util\StaticClassTrait;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class AllOptionsMetadata
{
    use StaticClassTrait;

    /**
     * @var ?array<string, OptionMetadata<mixed>>
     */
    private static $vaLue = null;

    /**
     * @return array<string, OptionMetadata<mixed>> Option name to metadata
     */
    public static function get(): array
    {
        if (self::$vaLue !== null) {
            return self::$vaLue;
        }

        /** @phpstan-ignore-next-line */
        self::$vaLue = [
            OptionNames::API_KEY                  => new NullableStringOptionMetadata(),
            OptionNames::ASYNC_BACKEND_COMM       => new BoolOptionMetadata(/* defaultValue: */ true),
            OptionNames::BREAKDOWN_METRICS        => new BoolOptionMetadata(/* defaultValue: */ true),
            OptionNames::CAPTURE_ERRORS           => new BoolOptionMetadata(/* defaultValue: */ true),
            OptionNames::DEV_INTERNAL             => new NullableWildcardListOptionMetadata(),
            OptionNames::DISABLE_INSTRUMENTATIONS => new NullableWildcardListOptionMetadata(),
            OptionNames::DISABLE_SEND             => new BoolOptionMetadata(/* defaultValue: */ false),
            OptionNames::ENABLED                  => new BoolOptionMetadata(/* defaultValue: */ true),
            OptionNames::ENVIRONMENT              => new NullableStringOptionMetadata(),
            OptionNames::HOSTNAME                 => new NullableStringOptionMetadata(),
            OptionNames::LOG_LEVEL                => new NullableLogLevelOptionMetadata(),
            OptionNames::LOG_LEVEL_STDERR         => new NullableLogLevelOptionMetadata(),
            OptionNames::LOG_LEVEL_SYSLOG         => new NullableLogLevelOptionMetadata(),
            OptionNames::NON_KEYWORD_STRING_MAX_LENGTH
                                                  => new IntOptionMetadata(
                                                      0 /* <- minValidValue */,
                                                      null /* <- maxValidValue */,
                                                      10 * 1024 /* <- defaultValue */
                                                  ),
            OptionNames::PROFILING_INFERRED_SPANS_ENABLED
                                                  => new BoolOptionMetadata(/* defaultValue: */ false),
            OptionNames::PROFILING_INFERRED_SPANS_MIN_DURATION
                                                  => new DurationOptionMetadata(
                                                      0.0 /* <- minValidValueInMilliseconds */,
                                                      null /* <- maxValidValueInMilliseconds */,
                                                      DurationUnits::MILLISECONDS /* <- defaultUnits */,
                                                      0.0 /* <- defaultValueInMilliseconds - 0ms */
                                                  ),
            OptionNames::PROFILING_INFERRED_SPANS_SAMPLING_INTERVAL
                                                  => new DurationOptionMetadata(
                                                      1000.0 /* <- minValidValueInMilliseconds - 1s */,
                                                      null /* <- maxValidValueInMilliseconds */,
                                                      DurationUnits::MILLISECONDS /* <- defaultUnits */,
                                                      1000.0 /* <- defaultValueInMilliseconds - 1s */
                                                  ),
            // https://github.com/elastic/apm/blob/e1ac6ecc841b525148cb293df9d852994d877773/specs/agents/sanitization.md#sanitize_field_names-configuration
            OptionNames::SANITIZE_FIELD_NAMES     => new WildcardListOptionMetadata(
                WildcardListOptionParser::parseImpl(
                    'password, passwd, pwd, secret, *key, *token*, *session*, *credit*, *card*, *auth*, set-cookie'
                )
            ),
            OptionNames::SECRET_TOKEN             => new NullableStringOptionMetadata(),
            OptionNames::SERVER_TIMEOUT
                                                  => new DurationOptionMetadata(
                                                      0.0 /* <- minValidValueInMilliseconds */,
                                                      null /* <- maxValidValueInMilliseconds */,
                                                      DurationUnits::SECONDS /* <- defaultUnits */,
                                                      30 * 1000.0 /* <- defaultValueInMilliseconds - 30s */
                                                  ),
            OptionNames::SERVICE_NAME             => new NullableStringOptionMetadata(),
            OptionNames::SERVICE_NODE_NAME        => new NullableStringOptionMetadata(),
            OptionNames::SERVICE_VERSION          => new NullableStringOptionMetadata(),
            OptionNames::TRANSACTION_IGNORE_URLS  => new NullableWildcardListOptionMetadata(),
            OptionNames::TRANSACTION_MAX_SPANS    => new IntOptionMetadata(
                0 /* <- minValidValue */,
                null /* <- maxValidValue */,
                OptionDefaultValues::TRANSACTION_MAX_SPANS
            ),
            OptionNames::TRANSACTION_SAMPLE_RATE  =>
                new FloatOptionMetadata(/* minValidValue */ 0.0, /* maxValidValue */ 1.0, /* defaultValue */ 1.0),
            OptionNames::URL_GROUPS               => new NullableWildcardListOptionMetadata(),
            OptionNames::VERIFY_SERVER_CERT       => new BoolOptionMetadata(/* defaultValue: */ true),
        ];

        return self::$vaLue; // @phpstan-ignore-line
    }
}
