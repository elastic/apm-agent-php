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

use Elastic\Apm\Impl\Util\ArrayUtil;

final class ArrayUtilTest extends TestCaseBase
{
    public static function testIsList(): void
    {
        self::assertTrue(ArrayUtil::isList([]));
        self::assertTrue(ArrayUtil::isList([1]));
        self::assertTrue(ArrayUtil::isList(['a', 'b']));
        self::assertTrue(ArrayUtil::isList(['different', 0, 'value', 1.23, 'types']));
        self::assertTrue(ArrayUtil::isList([0 => 'a']));
        self::assertTrue(ArrayUtil::isList([0 => 'a', 1 => 'b']));

        // The keys are not in the correct order
        self::assertFalse(ArrayUtil::isList([1 => 'b', 0 => 'a']));

        // The array does not start at 0
        self::assertFalse(ArrayUtil::isList([1 => 'b']));

        // Non-integer keys
        self::assertFalse(ArrayUtil::isList(['foo' => 'bar']));

        // Non-consecutive keys
        self::assertFalse(ArrayUtil::isList([0 => 'a', 2 => 'b']));
    }

    /**
     * @return iterable<array{mixed[], mixed[]}>
     */
    public function dataProviderForTestIterateListInReverse(): iterable
    {
        yield [[], []];
        yield [[1], [1]];
        yield [[1, 'b'], ['b', 1]];
    }

    /**
     * @dataProvider dataProviderForTestIterateListInReverse
     *
     * @param mixed[] $inputArray
     * @param mixed[] $expectedOutputArray
     *
     * @return void
     */
    public static function testIterateListInReverse(array $inputArray, array $expectedOutputArray): void
    {
        $pair = IterableUtilForTests::zip($expectedOutputArray, ArrayUtilForTests::iterateListInReverse($inputArray));
        foreach ($pair as [$expectedVal, $actualVal]) {
            self::assertSame($expectedVal, $actualVal);
        }
    }
}
