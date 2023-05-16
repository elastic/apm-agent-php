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

use Elastic\Apm\Impl\Log\LogCategory;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Log\LoggerFactory;
use Elastic\Apm\Impl\Util\TimeUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class Clock implements ClockInterface
{
    /** @var Logger */
    private $logger;

    /** @var bool */
    private $hasMonotonicTimeSource;

    /** @var ?float */
    private $lastSystemTime = null;

    /** @var ?float */
    private $lastMonotonicTime = null;

    public function __construct(LoggerFactory $loggerFactory)
    {
        $this->logger = $loggerFactory->loggerForClass(
            LogCategory::INFRASTRUCTURE,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        )->addContext('this', $this);

        $this->hasMonotonicTimeSource = function_exists('hrtime');
    }

    private function checkAgainstUpdateLast(string $dbgSourceDesc, float $current, /* ref */ ?float &$last): float
    {
        if ($last !== null) {
            if ($current < $last) {
                ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
                && $loggerProxy->log(
                    'Detected that clock has jumped backwards'
                    . ' - returning the later time (i.e., the time further into the future) instead',
                    [
                        'time source'         => $dbgSourceDesc,
                        'last as duration'    => TimeUtil::formatDurationInMicroseconds($last),
                        'current as duration' => TimeUtil::formatDurationInMicroseconds($current),
                        'current - last'      => TimeUtil::formatDurationInMicroseconds($current - $last),
                        'last as number'      => number_format($last),
                        'current as number'   => number_format($current),
                    ]
                );
                return $last;
            }
        }
        $last = $current;
        return $current;
    }

    /** @inheritDoc */
    public function getSystemClockCurrentTime(): float
    {
        // Return value should be in microseconds
        // while microtime(/* as_float: */ true) returns in seconds with microseconds being the fractional part
        return $this->checkAgainstUpdateLast(
            'microtime',
            round(TimeUtil::secondsToMicroseconds(microtime(/* as_float: */ true))),
            /* ref */ $this->lastSystemTime
        );
    }

    /** @inheritDoc */
    public function getMonotonicClockCurrentTime(): float
    {
        if ($this->hasMonotonicTimeSource) {
            /**
             * hrtime is available from PHP 7.3.0 (https://www.php.net/manual/en/function.hrtime.php)
             * and we support 7.2.* so we suppress static analysis warning
             * but at runtime we call hrtime only if it exists
             *
             * hrtime(true): the nanoseconds are returned as integer (64bit platforms) or float (32bit platforms)
             *
             * @see getMonotonicClockCurrentTime
             *
             * @phpstan-ignore-next-line
             * @var float $hrtimeRetVal
             */
            $hrtimeRetVal = hrtime(/* as_number */ true);

            return $this->checkAgainstUpdateLast(
                'hrtime',
                round(TimeUtil::nanosecondsToMicroseconds($hrtimeRetVal)),
                /* ref */ $this->lastMonotonicTime
            );
        }

        return $this->getSystemClockCurrentTime();
    }
}
