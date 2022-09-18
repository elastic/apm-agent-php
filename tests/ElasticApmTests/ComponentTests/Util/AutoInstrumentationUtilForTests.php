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

use Elastic\Apm\Impl\Util\StaticClassTrait;

final class AutoInstrumentationUtilForTests
{
    use StaticClassTrait;

    public const DISABLE_INSTRUMENTATIONS_KEY = 'DISABLE_INSTRUMENTATIONS';
    public const IS_INSTRUMENTATION_ENABLED_KEY = 'IS_INSTRUMENTATION_ENABLED';

    /**
     * @param array<string, bool> $disableInstrumentationsVariants
     *
     * @return callable(array<mixed, mixed>): iterable<array<mixed, mixed>>
     */
    public static function disableInstrumentationsDataProviderGenerator(
        array $disableInstrumentationsVariants
    ): callable {
        /**
         * @param array<mixed, mixed> $resultSoFar
         *
         * @return iterable<array<mixed, mixed>>
         */
        return function (array $resultSoFar) use ($disableInstrumentationsVariants): iterable {
            foreach ($disableInstrumentationsVariants as $optVal => $isInstrumentationEnabled) {
                yield array_merge(
                    $resultSoFar,
                    [
                        self::DISABLE_INSTRUMENTATIONS_KEY   => $optVal,
                        self::IS_INSTRUMENTATION_ENABLED_KEY => $isInstrumentationEnabled,
                    ]
                );
            }
        };
    }
}
