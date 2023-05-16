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

use Elastic\Apm\Impl\Util\RangeUtil;
use ElasticApmTests\Util\IterableUtilForTests;
use PHPUnit\Framework\TestCase;

class RangeUtilTest extends TestCase
{
    public function testGenerate(): void
    {
        /**
         * @param int $begin
         * @param int $end
         * @param int $step
         *
         * @return array<int>
         */
        $generateAsArray = function (int $begin, int $end, int $step = 1): array {
            return IterableUtilForTests::toList(RangeUtil::generate($begin, $end, $step));
        };

        self::assertEquals([], $generateAsArray(0, 0));
        self::assertEquals([], $generateAsArray(0, 0, 0));
        self::assertEquals([], $generateAsArray(0, 0, 1));
        self::assertEquals([], $generateAsArray(0, 0, -1));

        self::assertEquals([], $generateAsArray(100, 100));
        self::assertEquals([], $generateAsArray(100, 100, 0));
        self::assertEquals([], $generateAsArray(100, 100, 1));
        self::assertEquals([], $generateAsArray(100, 100, -1));

        self::assertEquals([0], $generateAsArray(0, 1));
        self::assertEquals([0, 1], $generateAsArray(0, 2));

        self::assertEquals([-1], $generateAsArray(-1, 0));
        self::assertEquals([-2, -1], $generateAsArray(-2, 0));
        self::assertEquals([-2], $generateAsArray(-2, 0, 2));

        self::assertEquals([], $generateAsArray(0, -1));
        self::assertEquals([], $generateAsArray(0, -1, 1));
        self::assertEquals([], $generateAsArray(0, -1, -1));

        self::assertEquals([], $generateAsArray(0, -2));
        self::assertEquals([], $generateAsArray(0, -2, 1));
        self::assertEquals([], $generateAsArray(0, -2, -1));
        self::assertEquals([], $generateAsArray(0, -2, 2));
        self::assertEquals([], $generateAsArray(0, -2, -2));

        self::assertEquals([101, 102], $generateAsArray(101, 103));
        self::assertEquals([-103, -102], $generateAsArray(-103, -101));
        self::assertEquals([], $generateAsArray(-101, -103));

        self::assertTrue(IterableUtilForTests::isEmpty(RangeUtil::generate(1000, 1000)));
        self::assertFalse(IterableUtilForTests::isEmpty(RangeUtil::generate(1000, 1001)));

        self::assertSame(0, IterableUtilForTests::count(RangeUtil::generate(1000, 1000)));
        self::assertSame(1, IterableUtilForTests::count(RangeUtil::generate(1000, 1001)));
    }

    public function testGenerateDown(): void
    {
        /**
         * @param int $begin
         * @param int $end
         * @param int $step
         *
         * @return array<int>
         */
        $generateDownAsArray = function (int $begin, int $end, int $step = 1): array {
            return IterableUtilForTests::toList(RangeUtil::generateDown($begin, $end, $step));
        };

        self::assertEquals([], $generateDownAsArray(0, 0));
        self::assertEquals([], $generateDownAsArray(0, 0, 0));
        self::assertEquals([], $generateDownAsArray(0, 0, 1));
        self::assertEquals([], $generateDownAsArray(0, 0, -1));

        self::assertEquals([], $generateDownAsArray(100, 100));
        self::assertEquals([], $generateDownAsArray(100, 100, 0));
        self::assertEquals([], $generateDownAsArray(100, 100, 1));
        self::assertEquals([], $generateDownAsArray(100, 100, -1));

        self::assertEquals([1], $generateDownAsArray(1, 0));
        self::assertEquals([2, 1], $generateDownAsArray(2, 0));

        self::assertEquals([0], $generateDownAsArray(0, -1));
        self::assertEquals([0, -1], $generateDownAsArray(0, -2));
        self::assertEquals([0], $generateDownAsArray(0, -2, 2));

        self::assertEquals([], $generateDownAsArray(-1, 0));
        self::assertEquals([], $generateDownAsArray(-1, 0, 1));
        self::assertEquals([], $generateDownAsArray(-1, 0, -1));

        self::assertEquals([], $generateDownAsArray(-2, 0));
        self::assertEquals([], $generateDownAsArray(-2, 0, 1));
        self::assertEquals([], $generateDownAsArray(-2, 0, -1));
        self::assertEquals([], $generateDownAsArray(-2, 0, 2));
        self::assertEquals([], $generateDownAsArray(-2, 0, -2));

        self::assertEquals([103, 102], $generateDownAsArray(103, 101));
        self::assertEquals([-101, -102], $generateDownAsArray(-101, -103));
        self::assertEquals([], $generateDownAsArray(-103, -101));

        self::assertTrue(IterableUtilForTests::isEmpty(RangeUtil::generateDown(1000, 1000)));
        self::assertFalse(IterableUtilForTests::isEmpty(RangeUtil::generateDown(1001, 1000)));

        self::assertSame(0, IterableUtilForTests::count(RangeUtil::generateDown(1000, 1000)));
        self::assertSame(1, IterableUtilForTests::count(RangeUtil::generateDown(1001, 1000)));
    }

    public function testGenerateUpTo(): void
    {
        /**
         * @param int $count
         *
         * @return array<int>
         */
        $generateUpToAsArray = function (int $count): array {
            return IterableUtilForTests::toList(RangeUtil::generateUpTo($count));
        };

        self::assertEquals([], $generateUpToAsArray(0));
        self::assertEquals([0], $generateUpToAsArray(1));
        self::assertEquals([0, 1], $generateUpToAsArray(2));
    }

    public function testGenerateFromToIncluding(): void
    {
        /**
         * @param int $begin
         * @param int $end
         *
         * @return array<int>
         */
        $generateFromToIncludingAsArray = function (int $begin, int $end): array {
            return IterableUtilForTests::toList(RangeUtil::generateFromToIncluding($begin, $end));
        };

        self::assertEquals([0], $generateFromToIncludingAsArray(0, 0));
        self::assertEquals([0, 1], $generateFromToIncludingAsArray(0, 1));
        self::assertEquals([0, 1, 2], $generateFromToIncludingAsArray(0, 2));
    }
}
