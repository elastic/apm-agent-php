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

namespace ElasticApmTests\UnitTests\UtilTests;

use Elastic\Apm\Impl\Util\RandomUtil;
use PHPUnit\Framework\TestCase;

class RandomUtilGenerate01FloatTest extends TestCase
{
    public function testRange(): void
    {
        for ($i = 0; $i < 10000; ++$i) {
            $value = RandomUtil::generate01Float();
            self::assertGreaterThanOrEqual(0.0, $value);
            self::assertLessThanOrEqual(1.0, $value);
        }
    }

    public function testDistribution(): void
    {
        $count = 20000;
        $sum = 0.0;
        $below = 0;
        for ($i = 0; $i < $count; ++$i) {
            $value = RandomUtil::generate01Float();
            $sum += $value;
            if ($value < 0.1) {
                ++$below;
            }
        }
        // Expected mean 0.5 (std dev of the mean ~0.002) and 10% of values below 0.1 (std dev ~0.002)
        self::assertEqualsWithDelta(0.5, $sum / $count, 0.02);
        self::assertEqualsWithDelta(0.1, $below / $count, 0.02);
    }

    public function testNotAffectedByMtSrand(): void
    {
        // Seeding mt_rand() with a constant would make the old implementation return the same value every time
        $values = [];
        for ($i = 0; $i < 100; ++$i) {
            mt_srand(1);
            $values[(string)RandomUtil::generate01Float()] = true;
        }
        mt_srand();
        self::assertGreaterThan(90, count($values));
    }
}
