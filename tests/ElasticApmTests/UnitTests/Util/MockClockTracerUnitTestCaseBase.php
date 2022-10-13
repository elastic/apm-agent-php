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

use Closure;
use ElasticApmTests\Util\TracerBuilderForTests;

class MockClockTracerUnitTestCaseBase extends TracerUnitTestCaseBase
{
    /** @var MockClock */
    protected $mockClock;

    /**
     * @return float
     */
    protected function mockClockInitialTimestamp(): float
    {
        return 0;
    }

    /** @inheritDoc */
    protected function setUpTestEnv(?Closure $tracerBuildCallback = null, bool $shouldCreateMockEventSink = true): void
    {
        $this->mockClock = new MockClock($this->mockClockInitialTimestamp());
        parent::setUpTestEnv(
            function (TracerBuilderForTests $tracerBuilder) use ($tracerBuildCallback): void {
                if ($tracerBuildCallback !== null) {
                    $tracerBuildCallback($tracerBuilder);
                }
                $tracerBuilder->withClock($this->mockClock);
            },
            $shouldCreateMockEventSink
        );
    }
}
