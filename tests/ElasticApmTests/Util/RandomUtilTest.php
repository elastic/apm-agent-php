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

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace ElasticApmTests\Util;

use Elastic\Apm\Impl\Log\LoggableToString;

class RandomUtilTest extends TestCaseBase
{
    public function testArrayRandValues(): void
    {
        self::assertSame([], RandomUtilForTests::arrayRandValues([], 0));
        self::assertSame([], RandomUtilForTests::arrayRandValues(['a'], 0));
        self::assertSame(['a'], RandomUtilForTests::arrayRandValues(['a'], 1));

        $totalSet = ['a', 'b'];
        $randSelectedSubSet = RandomUtilForTests::arrayRandValues($totalSet, 1);
        self::assertTrue(
            $randSelectedSubSet == ['a'] || $randSelectedSubSet == ['b'],
            LoggableToString::convert(['$randSelectedSubSet' => $randSelectedSubSet])
        );
        self::assertListArrayIsSubsetOf($randSelectedSubSet, $totalSet);
        self::assertEqualsCanonicalizing($totalSet, RandomUtilForTests::arrayRandValues($totalSet, count($totalSet)));

        $totalSet = ['a', 'b', 'c'];
        $randSelectedSubSet = RandomUtilForTests::arrayRandValues($totalSet, 1);
        self::assertCount(1, $randSelectedSubSet);
        self::assertTrue(
            $randSelectedSubSet == ['a'] || $randSelectedSubSet == ['b'] || $randSelectedSubSet == ['c'],
            LoggableToString::convert(['$randSelectedSubSet' => $randSelectedSubSet])
        );
        self::assertListArrayIsSubsetOf($randSelectedSubSet, $totalSet);
        $randSelectedSubSet = RandomUtilForTests::arrayRandValues($totalSet, 2);
        self::assertCount(2, $randSelectedSubSet);
        self::assertListArrayIsSubsetOf($randSelectedSubSet, $totalSet);
        self::assertEqualsCanonicalizing($totalSet, RandomUtilForTests::arrayRandValues($totalSet, count($totalSet)));
    }
}
