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

use Elastic\Apm\Impl\Log\LoggableToString;
use Elastic\Apm\Impl\Util\WildcardMatcher;
use ElasticApmTests\ExternalTestData;
use PHPUnit\Framework\TestCase;

class WildcardMatcherTest extends TestCase
{
    private function testCaseImpl(string $expr, string $text, bool $expectedResult): void
    {
        $this->assertSame(
            $expectedResult,
            (new WildcardMatcher($expr))->match($text),
            LoggableToString::convert(
                [
                    'expr' => $expr,
                    'text' => $text,
                    'expectedResult' => $expectedResult,
                ]
            )
        );
    }

    /**
     * @return iterable<array{string, string, string, bool}>
     */
    public function dataProviderForTestOnExternalData(): iterable
    {
        $externalDataJson = ExternalTestData::readJsonSpecsFile('wildcard_matcher_tests.json');
        self::assertIsArray($externalDataJson);
        foreach ($externalDataJson as $testDesc => $testCases) {
            foreach ($testCases as $expr => $textToExpectedResultPairs) {
                foreach ($textToExpectedResultPairs as $text => $expectedResult) {
                    yield [$testDesc, $expr, $text, $expectedResult];
                }
            }
        }
    }

    /**
     * @dataProvider dataProviderForTestOnExternalData
     *
     * @param string $testCaseDesc
     * @param string $expr
     * @param string $text
     * @param bool   $expectedResult
     */
    public function testOnExternalData(string $testCaseDesc, string $expr, string $text, bool $expectedResult): void
    {
        $this->assertNotSame('', $testCaseDesc);
        $this->testCaseImpl($expr, $text, $expectedResult);
    }

    /**
     * @return iterable<array{string, string, bool}>
     */
    public function dataProviderForTestAdditionalCases(): iterable
    {
        //
        // empty wildcard expression matches only empty text
        //
        yield ['', '', true];
        yield ['', '1', false];
        yield ['', '*', false];
        yield ['(?-i)', '', true];
        yield ['(?-i)', '1', false];
        yield ['(?-i)', '*', false];

        //
        // (?-i) prefix is not matched literally
        //
        yield ['(?-i)', '(?-i)', false];
        yield ['', '(?-i)', false];
    }

    /**
     * @dataProvider dataProviderForTestAdditionalCases
     *
     * @param string $expr
     * @param string $text
     * @param bool   $expectedResult
     */
    public function testAdditionalCases(string $expr, string $text, bool $expectedResult): void
    {
        $this->testCaseImpl($expr, $text, $expectedResult);
    }
}
