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

namespace Elastic\Apm\Impl;

use Elastic\Apm\Impl\Util\SingletonInstanceTrait;
use Elastic\Apm\Impl\Util\TimeUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class Clock implements ClockInterface
{
    use SingletonInstanceTrait;

    /** @var bool */
    private $hrtimeExits;

    public function __construct()
    {
        $this->hrtimeExits = function_exists('hrtime');
    }

    /** @inheritDoc */
    public function getSystemClockCurrentTime(): float
    {
        // Return value should be in microseconds
        // while microtime(/* as_float: */ true) returns in seconds with microseconds being the fractional part
        return round(TimeUtil::secondsToMicroseconds(microtime(/* as_float: */ true)));
    }

    /** @inheritDoc */
    public function getMonotonicClockCurrentTime(): float
    {
        return $this->hrtimeExits ? self::getHighResolutionCurrentTime() : $this->getSystemClockCurrentTime();
    }

    private static function getHighResolutionCurrentTime(): float
    {
        // hrtime(/* get_as_number */ true):
        //      the nanoseconds are returned as integer (64bit platforms) or float (32bit platforms)
        /**
         * hrtime is available from PHP 7.3.0 (https://www.php.net/manual/en/function.hrtime.php) and we support 7.2.*
         * so we suppress static analysis warning
         * but at runtime we call hrtime only if it exists
         *
         * @see getMonotonicClockCurrentTime
         *
         * @phpstan-ignore-next-line
         */
        return round(TimeUtil::nanosecondsToMicroseconds((float)(hrtime(/* as_number */ true))));
    }
}
