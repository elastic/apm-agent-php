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
     * @return iterable<array{string, array{string, string}[]}>
     *                                              ^^^^^^------- end-of-line
     *                                      ^^^^^^--------------- line text without end-of-line
     *                        ^^^^^^----------------------------- input text
     */
    public function dataProviderForTestIterateLines(): iterable
    {
        yield [
            '' /* empty line without end-of-line */,
            [['' /* <- empty line text */, '' /* <- no end-of-line */]]
        ];

        yield [
            'some text without end-of-line',
            [['some text without end-of-line', '' /* <- no end-of-line */]]
        ];

        yield [
            PHP_EOL /* <- empty line */ .
            'second line' . PHP_EOL .
            PHP_EOL /* <- empty line */ .
            'last non-empty line' . PHP_EOL
            /* empty line without end-of-line */,
            [
                ['' /* <- empty line text */, PHP_EOL],
                ['second line', PHP_EOL],
                ['' /* <- empty line text */, PHP_EOL],
                ['last non-empty line', PHP_EOL],
                ['' /* <- empty line text */, '' /* <- no end-of-line */],
            ],
        ];

        yield ["\n", [['' /* <- empty line text */, "\n"], ['' /* <- empty line text */, '' /* <- no end-of-line */]]];
        yield ["\r", [['' /* <- empty line text */, "\r"], ['' /* <- empty line text */, '' /* <- no end-of-line */]]];
        yield ["\r\n", [['' /* <- empty line text */, "\r\n"], ['' /* <- empty line text */, '' /* <- no end-of-line */]]];

        // "\n\r" is not one line end-of-line but two "\n\r"
        yield ["\n\r", [['' /* <- empty line text */, "\n"], ['' /* <- empty line text */, "\r"], ['' /* <- empty line text */, '']]];
    }

    /**
     * @dataProvider dataProviderForTestIterateLines
     *
     * @param string                  $inputText
     * @param array{string, string}[] $expectedLinesParts
     *                      ^^^^^^------------------------------ end-of-line
     *              ^^^^^^-------------------------------------- line text without end-of-line
     */
    public function testIterateLines(string $inputText, array $expectedLinesParts): void
    {
        foreach ([true, false] as $keepEndOfLine) {
            $index = 0;
            foreach (TextUtilForTests::iterateLines($inputText, $keepEndOfLine) as $actualLine) {
                $expectedLineParts = $expectedLinesParts[ $index ];
                self::assertCount(2, $expectedLineParts);
                $expectedLine = $expectedLineParts[0] . ($keepEndOfLine ? $expectedLineParts[1] : '');
                self::assertSame($expectedLine, $actualLine);
                ++$index;
            }
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
