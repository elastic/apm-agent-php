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

final class TextUtiTest extends TestCaseBase
{
    /**
     * @return iterable<array{string, string[]}>
     */
    public function dataProviderForTestIterateLines(): iterable
    {
        yield ['', ['']];
        yield ["\n", ["\n", '']];
        yield ["\r", ["\r", '']];
        yield ["\r\n", ["\r\n", '']];
        yield ["\n\r", ["\n", "\r", '']];
    }

    /**
     * @dataProvider dataProviderForTestIterateLines
     *
     * @param string   $inputText
     * @param string[] $expectedLines
     *
     * @return void
     */
    public function testIterateLines(string $inputText, array $expectedLines): void
    {
        $actualLinesCount = IterableUtilForTests::count(TextUtilForTests::iterateLines($inputText));
        self::assertSame(count($expectedLines), $actualLinesCount);

        $index = 0;
        foreach (TextUtilForTests::iterateLines($inputText) as $actualLine) {
            self::assertSame($expectedLines[$index], $actualLine);
            ++$index;
        }
    }

    /**
     * @return iterable<array{string, string, string}>
     */
    public function dataProviderForTestPrefixEachLine(): iterable
    {
        yield ['', 'p_', 'p_'];
        yield ["\n", 'p_', "p_\np_"];
        yield ["\r", 'p_', "p_\rp_"];
        yield ["\r\n", 'p_', "p_\r\np_"];
        yield ["\n\r", 'p_', "p_\np_\rp_"];
    }

    /**
     * @dataProvider dataProviderForTestPrefixEachLine
     *
     * @param string $inputText
     * @param string $prefix
     * @param string $expectedOutputText
     *
     * @return void
     */
    public function testPrefixEachLine(string $inputText, string $prefix, string $expectedOutputText): void
    {
        $actualOutputText = TextUtilForTests::prefixEachLine($inputText, $prefix);
        self::assertSame($expectedOutputText, $actualOutputText);
    }
}
