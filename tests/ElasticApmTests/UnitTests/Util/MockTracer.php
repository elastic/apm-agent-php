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
use Elastic\Apm\Impl\Constants;

final class MockTracer
{
    /** @var ClockInterface */
    private $clock;

    /** @var int */
    private $nextExecutionSegmentId = 1;

    /** @var int */
    private $nextTraceId = 1;

    public function __construct(ClockInterface $clock)
    {
        $this->clock = $clock;
    }

    public function beginTransaction(?string $name = null): MockTransaction
    {
        return new MockTransaction($name, $this, /* parent */ null);
    }

    public function getCurrentTime(): float
    {
        return $this->clock->getSystemClockCurrentTime();
    }

    public function generateExecutionSegmentId(): string
    {
        $id = ($this->nextExecutionSegmentId++);
        return self::convertToHexString($id, Constants::EXECUTION_SEGMENT_ID_SIZE_IN_BYTES, STR_PAD_LEFT);
    }

    public function generateTraceId(): string
    {
        $id = ($this->nextTraceId++);
        return self::convertToHexString($id, Constants::TRACE_ID_SIZE_IN_BYTES, STR_PAD_RIGHT);
    }

    private static function convertToHexString(int $id, int $idLengthInBytes, int $padType): string
    {
        $idAsHexString = sprintf('%x', $id);
        // $idLengthInBytes*2 because each byte is represented by 2 chars
        return str_pad($idAsHexString, $idLengthInBytes * 2, /* pad_string: */ '0', $padType);
    }
}
