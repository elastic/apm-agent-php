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

namespace ElasticApmTests\UnitTests\ConfigTests;

use Elastic\Apm\Impl\Config\WildcardListOptionParser;
use Elastic\Apm\Impl\Log\LoggableToString;
use PHPUnit\Framework\TestCase;

class WildcardListOptionParserTest extends TestCase
{
    private function testCaseImpl(string $exprList, string $text, ?string $expectedMatchingExpr): void
    {
        $matcher = (new WildcardListOptionParser())->parse($exprList);
        $actualMatchingExpr = $matcher->match($text);

        $this->assertSame(
            $expectedMatchingExpr,
            $actualMatchingExpr,
            LoggableToString::convert(
                [
                    'exprList'             => $exprList,
                    'text'                 => $text,
                    'expectedMatchingExpr' => $expectedMatchingExpr,
                    'actualMatchingExpr'   => $actualMatchingExpr,
                ]
            )
        );
    }

    public function testMatchedInOrderConfigured(): void
    {
        $this->testCaseImpl('/A/*, /A/B/*, /A/B/C/*', '/A/xyz', '/A/*');
        $this->testCaseImpl('/A/*, /A/B/*, /A/B/C/*', '/A/B/xyz', '/A/*');
        $this->testCaseImpl('/A/*, /A/B/*, /A/B/C/*', '/A/B/C/xyz', '/A/*');

        $this->testCaseImpl('/A/B/*, /A/B/C/*, /A/*', '/A/xyz', '/A/*');
        $this->testCaseImpl('/A/B/*, /A/B/C/*, /A/*', '/A/B/yz', '/A/B/*');
        $this->testCaseImpl('/A/B/*, /A/B/C/*, /A/*', '/A/B/C/xyz', '/A/B/*');

        $this->testCaseImpl('/A/B/C/*, /A/B/*, /A/*', '/A/xyz', '/A/*');
        $this->testCaseImpl('/A/B/C/*, /A/B/*, /A/*', '/A/B/xyz', '/A/B/*');
        $this->testCaseImpl('/A/B/C/*, /A/B/*, /A/*', '/A/B/C/xyz', '/A/B/C/*');
    }

    public function testCaseSensitiveIsSeparateForEachExpression(): void
    {
        $this->testCaseImpl('A, B', 'A', 'A');
        $this->testCaseImpl('A, B', 'a', 'A');
        $this->testCaseImpl('A, B', 'b', 'B');

        $this->testCaseImpl('(?-i)A, B', 'a', null);
        $this->testCaseImpl('(?-i)A, B', 'A', 'A');
        $this->testCaseImpl('(?-i)A, B', 'b', 'B');

        $this->testCaseImpl('(?-i)A, (?-i)B', 'b', null);
        $this->testCaseImpl('(?-i)A, (?-i)B', 'B', 'B');
    }

    public function testWhitespaceAroundCommasIsInsignificant(): void
    {
        $this->testCaseImpl(' A', ' A', null);
        $this->testCaseImpl(' A', 'A', 'A');
        $this->testCaseImpl(' *A', ' A', '*A');

        $this->testCaseImpl(' A, B ', ' A', null);
        $this->testCaseImpl(' A, B ', 'B ', null);
        $this->testCaseImpl(' A, B ', 'A', 'A');
        $this->testCaseImpl(' A, B ', 'B', 'B');

        $this->testCaseImpl(' *A* ', 'A', '*A*');

        $this->testCaseImpl("\t /*/A/ /*\n, / /B/*", '/xyz/A/ /', '/*/A/ /*');
        $this->testCaseImpl("\t /*/A/ /*\n, / /B/*", '/xyz/A/', null);
        $this->testCaseImpl("\t /*/A/ /*\n, / /B/*", '/ /B/xyz', '/ /B/*');
        $this->testCaseImpl("\t /*/A/ /*\n, / /B/*", '/ /B/', '/ /B/*');
        $this->testCaseImpl("\t /*/A/ /*\n, / /B/*", '/B/', null);
    }

    public function testToString(): void
    {
        $impl = function (string $exprList, string $expectedToStringResult): void {
            $actualToStringResult = strval((new WildcardListOptionParser())->parse($exprList));
            $this->assertSame(
                $expectedToStringResult,
                $actualToStringResult,
                LoggableToString::convert(
                    [
                        'expr'                   => $exprList,
                        'expectedToStringResult' => $expectedToStringResult,
                        'actualToStringResult'   => $actualToStringResult,
                    ]
                )
            );
        };

        $impl(/* input: */ "a, (?-i) a*b \t,  a**b, \n \t", /* expected: */ "a, (?-i) a*b, a*b, ");
    }
}
