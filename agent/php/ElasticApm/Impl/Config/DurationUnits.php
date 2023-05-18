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
use ElasticApmTests\UnitTests\UtilTests\TimeDurationUnitsTest;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class DurationUnits
{
    use StaticClassTrait;

    public const MILLISECONDS = 0;
    public const SECONDS = self::MILLISECONDS + 1;
    public const MINUTES = self::SECONDS + 1;

    public const MILLISECONDS_SUFFIX = 'ms';
    public const SECONDS_SUFFIX = 's';
    public const MINUTES_SUFFIX = 'm';

    /**
     * @var array<array{string, int}> Array should be in descending order of suffix length
     *
     * @see TimeDurationUnitsTest::testSuffixAndIdIsInDescendingOrderOfSuffixLength
     */
    public static $suffixAndIdPairs
        = [
            [self::MILLISECONDS_SUFFIX, self::MILLISECONDS],
            [self::SECONDS_SUFFIX, self::SECONDS],
            [self::MINUTES_SUFFIX, self::MINUTES],
        ];
}
