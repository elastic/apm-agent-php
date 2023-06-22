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

namespace ElasticApmTests\UnitTests;

use Elastic\Apm\Impl\Util\RangeUtil;
use ElasticApmTests\Util\AssertMessageStack;
use ElasticApmTests\Util\TestCaseBase;

final class BackendCommBackoffUnitTest extends TestCaseBase
{
    public static function expectedWaitTimeWithoutJitter(int $errorCount): int
    {
        /**
         *  The grace period should be calculated in seconds using the algorithm min(reconnectCount++, 6) ** 2 ± 10%
         *
         * @see https://github.com/elastic/apm/blob/d8cb5607dbfffea819ab5efc9b0743044772fb23/specs/agents/transport.md#transport-errors
         */

        self::assertGreaterThanOrEqual(0, $errorCount);
        $reconnectCount = $errorCount === 0 ? 0 : ($errorCount - 1);
        return pow(min($reconnectCount, 6), 2);
    }

    public function testExpectedWaitTimeWithoutJitter(): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, AssertMessageStack::funcArgs());

        /**
         *  the delay after the first error is 0 seconds, then circa 1, 4, 9, 16, 25 and finally 36 seconds
         *
         * @see https://github.com/elastic/apm/blob/d8cb5607dbfffea819ab5efc9b0743044772fb23/specs/agents/transport.md#transport-errors
         */
        $expectedWaitTimes = [0, 0, 1, 4, 9, 16, 25, 36, 36, 36];

        $dbgCtx->pushSubScope();
        foreach (RangeUtil::generateUpTo(count($expectedWaitTimes)) as $errorCount) {
            $dbgCtx->add(['errorCount' => $errorCount]);
            self::assertSame($expectedWaitTimes[$errorCount], self::expectedWaitTimeWithoutJitter($errorCount));
        }
        $dbgCtx->popSubScope();
    }
}
