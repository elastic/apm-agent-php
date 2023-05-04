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

use Elastic\Apm\Impl\Log\LoggableToString;
use Elastic\Apm\Impl\Util\RangeUtil;

class AssertMessageStackTest extends TestCaseBase
{
    /**
     * @param array{string, array<string, mixed>}[] $expected
     */
    private static function assertScopesStack(array $expected): void
    {
        $actual = array_reverse(AssertMessageStack::getScopeDataStack());
        $dbgCtx = ['expected' => $expected, 'actual' => $actual];
        self::assertSame(count($expected), count($actual), LoggableToString::convert($dbgCtx));
        $index = 0;
        foreach (IterableUtilForTests::zip($expected, $actual) as [$expectedScopeFuncCtxPair, $actualScopeData]) {
            /** @var array{string, array<string, mixed>} $expectedScopeFuncCtxPair */
            /** @var AssertMessageStackScopeData $actualScopeData */
            $dbgCtxPerIt = array_merge($dbgCtx, ['expectedScopeFuncCtxPair' => $expectedScopeFuncCtxPair, 'actualScopeData' => $actualScopeData]);
            self::assertStringContainsString($expectedScopeFuncCtxPair[0], $actualScopeData->name, LoggableToString::convert($dbgCtxPerIt));
            self::assertStringContainsString(basename(__FILE__), $actualScopeData->name, LoggableToString::convert($dbgCtxPerIt));
            self::assertStringContainsString(__CLASS__, $actualScopeData->name, LoggableToString::convert($dbgCtxPerIt));
            self::assertEqualMaps($expectedScopeFuncCtxPair[1], $actualScopeData->ctx);
            ++$index;
        }
    }

    /**
     * @param string                                $funcName
     * @param array{string, array<string, mixed>}[] $expectedScopesFromCaller
     *
     * @return array{string, array<string, mixed>}[]
     */
    private static function pushNewExpectedScope(string $funcName, array $expectedScopesFromCaller = []): array
    {
        $expectedScopes = $expectedScopesFromCaller;
        $expectedScopes[] = [$funcName, []];
        self::assertScopesStack($expectedScopes);
        return $expectedScopes;
    }

    /**
     * @param array{string, array<string, mixed>}[] &$expectedScopes
     * @param array<string, mixed>                   $ctx
     */
    private static function addToTopExpectedScope(/* ref */ array &$expectedScopes, array $ctx): void
    {
        self::assertNotEmpty($expectedScopes);
        $lastKey = array_key_last($expectedScopes);
        $expectedScopes[$lastKey][1] = array_merge($expectedScopes[$lastKey][1], $ctx);
        self::assertScopesStack($expectedScopes);
    }

    public function testOneFuncScope(): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx);
        $expectedScopes = self::pushNewExpectedScope(__FUNCTION__);
        $dbgCtx->add(['my_key' => 1]);
        self::addToTopExpectedScope(/* ref */ $expectedScopes, ['my_key' => 1]);
        $dbgCtx->add(['my_key' => 2]);
        self::addToTopExpectedScope(/* ref */ $expectedScopes, ['my_key' => 2]);
    }

    /**
     * @param array{string, array<string, mixed>}[] $expectedScopesFromCaller
     */
    private static function helperFuncForTestTwoFuncScopes(array $expectedScopesFromCaller): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx);
        $expectedScopes = self::pushNewExpectedScope(__FUNCTION__, $expectedScopesFromCaller);
        $dbgCtx->add(['location' => 'inside func']);
        self::addToTopExpectedScope(/* ref */ $expectedScopes, ['location' => 'inside func']);
    }

    public function testTwoFuncScopes(): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx);
        $expectedScopes = self::pushNewExpectedScope(__FUNCTION__);
        $dbgCtx->add(['location' => 'before calling func']);
        self::addToTopExpectedScope(/* ref */ $expectedScopes, ['location' => 'before calling func']);
        self::helperFuncForTestTwoFuncScopes($expectedScopes);
        $dbgCtx->add(['location' => 'after calling func']);
        self::addToTopExpectedScope(/* ref */ $expectedScopes, ['location' => 'after calling func']);
    }

    public function testSubScopeForLoopIteration(): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx);
        $expectedScopesOutsideLoop = self::pushNewExpectedScope(__FUNCTION__);
        $dbgCtx->add(['location' => 'before loop']);
        self::addToTopExpectedScope(/* ref */ $expectedScopesOutsideLoop, ['location' => 'before loop']);

        foreach (RangeUtil::generateUpTo(2) as $index) {
            AssertMessageStack::newSubScope(/* ref */ $dbgCtx);
            $expectedScopesInsideLoop = self::pushNewExpectedScope(__FUNCTION__, $expectedScopesOutsideLoop);
            $dbgCtx->add(['index' => $index, 'key_with_index_' . $index => 'value_with_index_' . $index]);
            self::addToTopExpectedScope(/* ref */ $expectedScopesInsideLoop, ['index' => $index, 'key_with_index_' . $index => 'value_with_index_' . $index]);
            AssertMessageStack::popSubScope(/* ref */ $dbgCtx);
        }

        $dbgCtx->add(['location' => 'after loop']);
        self::addToTopExpectedScope(/* ref */ $expectedScopesOutsideLoop, ['location' => 'after loop']);
    }

    public function testNewScopeInsideLoop(): void
    {
        foreach (RangeUtil::generateUpTo(2) as $index) {
            AssertMessageStack::newScope(/* out */ $dbgCtx);
            $expectedScopesInsideLoop = self::pushNewExpectedScope(__FUNCTION__, []);
            $dbgCtx->add(['index' => $index, 'key_with_index_' . $index => 'value_with_index_' . $index]);
            self::addToTopExpectedScope(/* ref */ $expectedScopesInsideLoop, ['index' => $index, 'key_with_index_' . $index => 'value_with_index_' . $index]);
        }
    }
}
