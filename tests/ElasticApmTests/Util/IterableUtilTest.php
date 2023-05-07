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

use Generator;

final class IterableUtilTest extends TestCaseBase
{
    /**
     * @return iterable<array{mixed[][], mixed[][]}>
     */
    public function dataProviderForTestZip(): iterable
    {
        yield [[[]], []];
        yield [[[], []], []];
        yield [[[], [], []], []];

        yield [[['a'], [1]], [['a', 1]]];
        yield [[['a', 'b'], [1, 2]], [['a', 1], ['b', 2]]];
        yield [[['a', 'b', 'c'], [1, 2, 3], [4.4, 5.5, 6.6]], [['a', 1, 4.4], ['b', 2, 5.5], ['c', 3, 6.6]]];
    }

    /**
     * @dataProvider dataProviderForTestZip
     *
     * @param mixed[][] $inputArrays
     * @param mixed[][] $expectedOutput
     *
     * @return void
     */
    public static function testZip(array $inputArrays, array $expectedOutput): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, AssertMessageStack::funcArgs());

        /**
         * @param iterable<mixed>[] $inputIterables
         * @param mixed[][]         $expectedOutput
         *
         * @return void
         */
        $test = function (array $inputIterables, array $expectedOutput): void {
            AssertMessageStack::newScope(/* out */ $dbgCtx, AssertMessageStack::funcArgs());
            $dbgCtx->add(['count($inputIterables)' => count($inputIterables)]);
            $i = 0;
            foreach (IterableUtilForTests::zip(...$inputIterables) as $actualTuple) {
                $dbgCtx->clearCurrentSubScope(['i' => $i, 'actualTuple' => $actualTuple]);
                self::assertLessThan(count($expectedOutput), $i);
                $expectedTuple = $expectedOutput[$i];
                self::assertEqualLists($expectedTuple, $actualTuple);
                ++$i;
            }
            self::assertSame(count($expectedOutput), $i);
        };

        $test($inputArrays, $expectedOutput);

        /**
         * @param mixed[] $inputArray
         *
         * @return Generator<mixed>
         */
        $arrayToGenerator = function (array $inputArray): iterable {
            foreach ($inputArray as $val) {
                yield $val;
            }
        };

        /** @var iterable<mixed>[] $inputArraysAsGenerators */
        $inputArraysAsGenerators = [];
        foreach ($inputArrays as $inputArray) {
            $inputArraysAsGenerators[] = $arrayToGenerator($inputArray);
        }
        $test($inputArraysAsGenerators, $expectedOutput);
    }
}
