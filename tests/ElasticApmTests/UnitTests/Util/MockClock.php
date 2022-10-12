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

namespace ElasticApmTests\UnitTests\Util;

use Elastic\Apm\Impl\ClockInterface;
use Elastic\Apm\Impl\Util\TimeUtil;
use ElasticApmTests\Util\PhpUnitExtensionBase;

class MockClock implements ClockInterface
{
    /** @var float */
    private $systemClockCurrentTime;

    /** @var float */
    private $monotonicClockCurrentTime;

    public function __construct(float $initial)
    {
        $this->systemClockCurrentTime = $initial;
        $this->monotonicClockCurrentTime = 10 * $initial;
        PhpUnitExtensionBase::$timestampBeforeTest = $initial;
    }

    public function getTimestamp(): float
    {
        return $this->getSystemClockCurrentTime();
    }

    public function fastForwardMicroseconds(float $numberOfMicroseconds): void
    {
        $this->systemClockCurrentTime += $numberOfMicroseconds;
        $this->monotonicClockCurrentTime += $numberOfMicroseconds;
        PhpUnitExtensionBase::$timestampAfterTest = $this->systemClockCurrentTime;
    }

    public function fastForwardMilliseconds(float $durationInMilliseconds): void
    {
        $this->fastForwardMicroseconds(TimeUtil::millisecondsToMicroseconds($durationInMilliseconds));
    }

    /** @inheritDoc */
    public function getSystemClockCurrentTime(): float
    {
        return $this->systemClockCurrentTime;
    }

    /** @inheritDoc */
    public function getMonotonicClockCurrentTime(): float
    {
        return $this->monotonicClockCurrentTime;
    }
}
