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

use Elastic\Apm\Impl\Log\Level as LogLevel;
use Elastic\Apm\Impl\Log\LoggableToString;
use ElasticApmTests\ComponentTests\Util\ComponentTestCaseBase;
use ElasticApmTests\Util\IterableUtilForTests;
use ElasticApmTests\Util\TestCaseBase;

/**
 * @group does_not_require_external_services
 */
final class ComponentTestsUtilUnitTest extends TestCaseBase
{
    /**
     * @return iterable<array{array<string, int>, array<array<string, int>>}>
     */
    public function dataProviderForTestGenerateEscalatedLogLevels(): iterable
    {
        $prodCodeKey = ComponentTestCaseBase::LOG_LEVEL_FOR_PROD_CODE_KEY;
        $testCodeKey = ComponentTestCaseBase::LOG_LEVEL_FOR_TEST_CODE_KEY;
        $highestLevel = LogLevel::getHighest();

        /**
         * When the initial already the highest
         */
        yield [[$prodCodeKey => $highestLevel, $testCodeKey => $highestLevel], []];

        /**
         * When the initial one step below the highest
         */
        yield [
            // initialLevels:
            [$prodCodeKey => $highestLevel - 1, $testCodeKey => $highestLevel],
            // expectedEscalatedLevelsSeq:
            [[$prodCodeKey => $highestLevel, $testCodeKey => $highestLevel]],
        ];

        yield [
            // initialLevels:
            [$prodCodeKey => $highestLevel, $testCodeKey => $highestLevel - 1],
            // expectedEscalatedLevelsSeq:
            [[$prodCodeKey => $highestLevel, $testCodeKey => $highestLevel]],
        ];

        /**
         * When the initial is the default
         */
        yield [
            // initialLevels:
            [$prodCodeKey => LogLevel::INFO, $testCodeKey => LogLevel::INFO],
            // expectedEscalatedLevelsSeq:
            [
                [$prodCodeKey => LogLevel::TRACE, $testCodeKey => LogLevel::TRACE],
                [$prodCodeKey => LogLevel::DEBUG, $testCodeKey => LogLevel::TRACE],
                [$prodCodeKey => LogLevel::TRACE, $testCodeKey => LogLevel::DEBUG],
                [$prodCodeKey => LogLevel::INFO, $testCodeKey => LogLevel::TRACE],
                [$prodCodeKey => LogLevel::TRACE, $testCodeKey => LogLevel::INFO],
                [$prodCodeKey => LogLevel::DEBUG, $testCodeKey => LogLevel::DEBUG],
                [$prodCodeKey => LogLevel::INFO, $testCodeKey => LogLevel::DEBUG],
                [$prodCodeKey => LogLevel::DEBUG, $testCodeKey => LogLevel::INFO],
            ]
        ];
    }

    /**
     * @dataProvider dataProviderForTestGenerateEscalatedLogLevels
     *
     * @param array<string, int>        $initialLevels
     * @param array<array<string, int>> $expectedLevelsSeq
     *
     * @return void
     */
    public function testGenerateEscalatedLogLevels(array $initialLevels, array $expectedLevelsSeq): void
    {
        $dbgCtx = ['initialLevels' => $initialLevels, 'expectedLevelsSeq' => $expectedLevelsSeq];
        $actualEscalatedLevelsSeq
            = IterableUtilForTests::toList(ComponentTestCaseBase::generateEscalatedLogLevels($initialLevels));
        $dbgCtx['actualEscalatedLevelsSeq'] = $actualEscalatedLevelsSeq;
        $i = 0;
        foreach ($actualEscalatedLevelsSeq as $actualLevels) {
            $dbgCtxPerIter = array_merge(['i' => $i, 'actualLevels' => $actualLevels], $dbgCtx);
            self::assertGreaterThan($i, count($expectedLevelsSeq), LoggableToString::convert($dbgCtxPerIter));
            $expectedLevels = $expectedLevelsSeq[$i];
            $dbgCtxPerIter['expectedLevels'] = $expectedLevels;
            self::assertCount(count($expectedLevels), $actualLevels, LoggableToString::convert($dbgCtxPerIter));
            foreach ($expectedLevels as $levelTypeKey => $expectedLevel) {
                $dbgCtxPerIter2 = array_merge(['levelTypeKey' => $levelTypeKey], $dbgCtxPerIter);
                $dbgCtxPerIter2Str = LoggableToString::convert($dbgCtxPerIter2);
                self::assertSame($expectedLevel, $actualLevels[$levelTypeKey], $dbgCtxPerIter2Str);
            }
            ++$i;
        }
        self::assertCount($i, $expectedLevelsSeq, LoggableToString::convert($dbgCtx));
    }
}
