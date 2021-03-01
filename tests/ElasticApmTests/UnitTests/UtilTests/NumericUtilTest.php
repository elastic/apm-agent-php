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

use Elastic\Apm\Impl\Util\NumericUtil;
use ElasticApmTests\Util\FloatLimits;
use PHPUnit\Framework\TestCase;

class NumericUtilTest extends TestCase
{
    public function testIsInClosedInterval(): void
    {
        // int - inside
        self::assertTrue(NumericUtil::isInClosedInterval(0, 1, 1));
        self::assertTrue(NumericUtil::isInClosedInterval(0, 0, 10));
        self::assertTrue(NumericUtil::isInClosedInterval(0, 1, 10));
        self::assertTrue(NumericUtil::isInClosedInterval(0, 5, 10));
        self::assertTrue(NumericUtil::isInClosedInterval(0, 10, 10));
        self::assertTrue(NumericUtil::isInClosedInterval(0, 0, 1));
        self::assertTrue(NumericUtil::isInClosedInterval(PHP_INT_MIN, 0, PHP_INT_MAX));
        self::assertTrue(NumericUtil::isInClosedInterval(PHP_INT_MIN, 1, PHP_INT_MAX));
        self::assertTrue(NumericUtil::isInClosedInterval(PHP_INT_MIN, -1, PHP_INT_MAX));
        self::assertTrue(NumericUtil::isInClosedInterval(PHP_INT_MIN, 123, PHP_INT_MAX));
        self::assertTrue(NumericUtil::isInClosedInterval(PHP_INT_MIN, -123, PHP_INT_MAX));
        self::assertTrue(NumericUtil::isInClosedInterval(PHP_INT_MIN, PHP_INT_MAX - 1, PHP_INT_MAX));
        self::assertTrue(NumericUtil::isInClosedInterval(PHP_INT_MIN, PHP_INT_MIN + 2, PHP_INT_MAX));
        self::assertTrue(NumericUtil::isInClosedInterval(PHP_INT_MIN, PHP_INT_MAX, PHP_INT_MAX));
        self::assertTrue(NumericUtil::isInClosedInterval(PHP_INT_MIN, PHP_INT_MIN, PHP_INT_MAX));

        // int - outside
        self::assertTrue(!NumericUtil::isInClosedInterval(0, 2, 1));
        self::assertTrue(!NumericUtil::isInClosedInterval(0, -1, 1));
        self::assertTrue(!NumericUtil::isInClosedInterval(0, -1, 10));
        self::assertTrue(!NumericUtil::isInClosedInterval(-10, -15, 10));

        // float - inside
        self::assertTrue(NumericUtil::isInClosedInterval(-20.5, 0, 10.5));
        self::assertTrue(NumericUtil::isInClosedInterval(-20.5, -20.5, 10.5));
        self::assertTrue(NumericUtil::isInClosedInterval(-20.5, 10.5, 10.5));
        self::assertTrue(NumericUtil::isInClosedInterval(-1.2, 3.4, 3.4));
        self::assertTrue(NumericUtil::isInClosedInterval(-1.2, 3.3, 3.4));
        self::assertTrue(NumericUtil::isInClosedInterval(-1.2, -1.2, 3.4));
        self::assertTrue(NumericUtil::isInClosedInterval(-1.2, -1.1, 3.4));
        self::assertTrue(NumericUtil::isInClosedInterval(FloatLimits::MIN, 0, FloatLimits::MAX));
        self::assertTrue(NumericUtil::isInClosedInterval(FloatLimits::MIN, 1.2, FloatLimits::MAX));
        self::assertTrue(NumericUtil::isInClosedInterval(FloatLimits::MIN, -1.2, FloatLimits::MAX));
        self::assertTrue(NumericUtil::isInClosedInterval(FloatLimits::MIN, 123.4, FloatLimits::MAX));
        self::assertTrue(NumericUtil::isInClosedInterval(FloatLimits::MIN, -123.4, FloatLimits::MAX));
        self::assertTrue(NumericUtil::isInClosedInterval(FloatLimits::MIN, FloatLimits::MAX - 0.1, FloatLimits::MAX));
        self::assertTrue(NumericUtil::isInClosedInterval(FloatLimits::MIN, FloatLimits::MIN + 0.1, FloatLimits::MAX));
        self::assertTrue(NumericUtil::isInClosedInterval(FloatLimits::MIN, FloatLimits::MAX, FloatLimits::MAX));
        self::assertTrue(NumericUtil::isInClosedInterval(FloatLimits::MIN, FloatLimits::MIN, FloatLimits::MAX));

        // float - outside
        self::assertTrue(!NumericUtil::isInClosedInterval(-1.2, -1.201, 3.4));
        self::assertTrue(!NumericUtil::isInClosedInterval(-1.2, 3.401, 3.4));
        self::assertTrue(!NumericUtil::isInClosedInterval(-20.5, -20.501, 10.5));
        self::assertTrue(!NumericUtil::isInClosedInterval(-20.5, 10.501, 10.5));
    }
}
