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
use ElasticApmTests\Util\IterableUtilForTests;

final class DbAutoInstrumentationUtilForTests
{
    use StaticClassTrait;

    public const HOST_KEY = 'HOST';
    public const PORT_KEY = 'PORT';
    public const USER_KEY = 'USER';
    public const PASSWORD_KEY = 'PASSWORD';

    public const DB_NAME_KEY = 'DB_NAME';
    public const WRAP_IN_TX_KEY = 'WRAP_IN_TX';
    public const ROLLBACK_KEY = 'ROLLBACK';
    public const CALL_END_TX_IN_SHUTDOWN_FUNCTION_KEY = 'CALL_END_TX_IN_SHUTDOWN_FUNCTION';

    /**
     * @return callable(array<mixed>): iterable<array<mixed>>
     */
    public static function wrapTxRelatedArgsDataProviderGenerator(): callable
    {
        /**
         * @param array<mixed> $resultSoFar
         *
         * @return iterable<array<mixed>>
         */
        return function (array $resultSoFar): iterable {
            foreach (IterableUtilForTests::ALL_BOOL_VALUES as $wrapInTx) {
                $rollbackValues = $wrapInTx ? [false, true] : [false];
                $callEndTxInShutdownFunctionValues = $wrapInTx ? [false, true] : [false];
                foreach ($rollbackValues as $rollback) {
                    foreach ($callEndTxInShutdownFunctionValues as $callEndTxInShutdownFunction) {
                        yield array_merge(
                            $resultSoFar,
                            [
                                self::WRAP_IN_TX_KEY                       => $wrapInTx,
                                self::ROLLBACK_KEY                         => $rollback,
                                self::CALL_END_TX_IN_SHUTDOWN_FUNCTION_KEY => $callEndTxInShutdownFunction,
                            ]
                        );
                    }
                }
            }
        };
    }
}
