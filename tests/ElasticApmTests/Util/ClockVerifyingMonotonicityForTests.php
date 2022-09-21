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

namespace ElasticApmTests\Util;

use Elastic\Apm\Impl\Clock;
use Elastic\Apm\Impl\ClockInterface;
use Elastic\Apm\Impl\Log\LoggableToString;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Util\SingletonInstanceTrait;
use Elastic\Apm\Impl\Util\TimeUtil;
use ElasticApmTests\ComponentTests\Util\AmbientContextForTests;
use PHPUnit\Framework\TestCase;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class ClockVerifyingMonotonicityForTests implements ClockInterface
{
    use SingletonInstanceTrait;

    /** @var Logger */
    private $logger;

    /** @var ?float */
    private $lastSystemTime = null;

    /** @var ?float */
    private $lastMonotonicTime = null;

    private function __construct()
    {
        $this->logger = AmbientContextForTests::loggerFactory()->loggerForClass(
            LogCategoryForTests::TEST_UTIL,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        )->addContext('this', $this);
    }

    private function checkAgainstUpdateLast(
        float $current,
        bool $isExpectedMonotonic,
        /* ref */ ?float &$last
    ): float {
        if ($last !== null) {
            if ($current + TestCaseBase::TIMESTAMP_COMPARISON_PRECISION_MICROSECONDS < $last) {
                $logCtx =                 [
                    'last as duration' => TimeFormatUtilForTests::formatDurationInMicroseconds($last),
                    'current as duration'  => TimeFormatUtilForTests::formatDurationInMicroseconds($current),
                    'current - last'     => TimeFormatUtilForTests::formatDurationInMicroseconds($current - $last),
                    'last as number'   => number_format($last),
                    'current as number'    => number_format($current),
                ];
                $msg = ($isExpectedMonotonic ? 'Monotonic' : 'System') . ' clock has gone backwards';
                ($loggerProxy = $this->logger->ifWarningLevelEnabled(__LINE__, __FUNCTION__))
                && $loggerProxy->log($msg, $logCtx);
                if ($isExpectedMonotonic) {
                    TestCaseBase::fail($msg . '; ' . LoggableToString::convert($logCtx));
                }
            }
        }
        $last = $current;
        return $current;
    }

    /** @inheritDoc */
    public function getSystemClockCurrentTime(): float
    {
        return $this->checkAgainstUpdateLast(
            Clock::singletonInstance()->getSystemClockCurrentTime(),
            /* isExpectedMonotonic */ false,
            /* ref */ $this->lastSystemTime
        );
    }

    /** @inheritDoc */
    public function getMonotonicClockCurrentTime(): float
    {
        return $this->checkAgainstUpdateLast(
            Clock::singletonInstance()->getMonotonicClockCurrentTime(),
            /* isExpectedMonotonic */ true,
            /* ref */ $this->lastMonotonicTime
        );
    }
}
