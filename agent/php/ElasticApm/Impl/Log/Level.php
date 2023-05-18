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

namespace Elastic\Apm\Impl\Log;

use Elastic\Apm\Impl\Util\StaticClassTrait;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class Level
{
    use StaticClassTrait;

    public const OFF = 0;
    public const CRITICAL = self::OFF + 1;
    public const ERROR = self::CRITICAL + 1;
    public const WARNING = self::ERROR + 1;
    public const INFO = self::WARNING + 1;
    public const DEBUG = self::INFO + 1;
    public const TRACE = self::DEBUG + 1;

    /** @var array<array{string, int}> */
    private static $nameIntPairs
        = [
            ['OFF', Level::OFF],
            ['CRITICAL', Level::CRITICAL],
            ['ERROR', Level::ERROR],
            ['WARNING', Level::WARNING],
            ['INFO', Level::INFO],
            ['DEBUG', Level::DEBUG],
            ['TRACE', Level::TRACE],
        ];

    /** @var ?array<int, string> */
    private static $intToName = null;

    /**
     * @return array<array{string, int}>
     */
    public static function nameIntPairs(): array
    {
        return self::$nameIntPairs;
    }

    public static function intToName(int $intValueToMap): string
    {
        if (self::$intToName === null) {
            self::$intToName = [];
            foreach (self::$nameIntPairs as $nameIntPair) {
                self::$intToName[$nameIntPair[1]] = $nameIntPair[0];
            }
        }
        return self::$intToName[$intValueToMap];
    }

    public static function getHighest(): int
    {
        return self::TRACE;
    }
}
