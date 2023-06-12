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
use PHPUnit\Framework\TestCase;

/**
 * This class extends TestCase and TestCaseBase on purpose because TestCaseBase uses AssertMessageStack
 */
class AssertMessageStackTest extends TestCase
{
    /**
     * @template TKey of array-key
     * @template TValue
     *
     * @param TKey                $expectedKey
     * @param TValue              $expectedVal
     * @param array<TKey, TValue> $actualArray
     */
    public static function assertSameValueInArray($expectedKey, $expectedVal, array $actualArray): void
    {
        self::assertArrayHasKey($expectedKey, $actualArray);
        self::assertSame($expectedVal, $actualArray[$expectedKey]);
    }

    /**
     * @param Pair<string, array<string, mixed>>[] $expected
     */
    private static function assertContextsStack(array $expected): void
    {
        $actual = AssertMessageStack::getContextsStack();
        $dbgCtx = ['expected' => $expected, 'actual' => $actual];
        self::assertSame(count($expected), count($actual), LoggableToString::convert($dbgCtx));
        foreach (IterableUtilForTests::zip(array_keys($expected), array_keys($actual)) as [$expectedCtxIndex, $actualCtxDesc]) {
            /** @var int $expectedCtxIndex */
            /** @var string $actualCtxDesc */
            $dbgCtxPerIt = array_merge($dbgCtx, ['expectedCtxIndex' => $expectedCtxIndex, 'actualCtxDesc' => $actualCtxDesc]);
            /** @var array{string, array<string, mixed>} $expectedCtx */
            $expectedCtxFuncName = $expected[$expectedCtxIndex]->first;
            $expectedCtx = $expected[$expectedCtxIndex]->second;
            $actualCtx = $actual[$actualCtxDesc];
            $dbgCtxPerIt = array_merge($dbgCtxPerIt, ['expectedCtxFuncName' => $expectedCtxFuncName, 'expectedCtx' => $expectedCtx, 'actualCtx' => $actualCtx]);
            self::assertStringContainsString($expectedCtxFuncName, $actualCtxDesc, LoggableToString::convert($dbgCtxPerIt));
            self::assertStringContainsString(basename(__FILE__), $actualCtxDesc, LoggableToString::convert($dbgCtxPerIt));
            self::assertStringContainsString(__CLASS__, $actualCtxDesc, LoggableToString::convert($dbgCtxPerIt));
            self::assertSame(count($expectedCtx), count($actualCtx), LoggableToString::convert($dbgCtxPerIt));
            foreach (IterableUtilForTests::zip(array_keys($expectedCtx), array_keys($actualCtx)) as [$expectedKey, $actualKey]) {
                $dbgCtxPerCtxKey = array_merge($dbgCtxPerIt, ['expectedKey' => $expectedKey, 'actualKey' => $actualKey]);
                self::assertSame($expectedKey, $actualKey, LoggableToString::convert($dbgCtxPerCtxKey));
                self::assertSame($expectedCtx[$expectedKey], $actualCtx[$actualKey], LoggableToString::convert($dbgCtxPerCtxKey));
            }
        }
    }

    /**
     * @param string                               $funcName
     * @param array<string, mixed>                 $initialCtx
     * @param Pair<string, array<string, mixed>>[] $expectedContextsStackFromCaller
     *
     * @return Pair<string, array<string, mixed>>[]
     */
    private static function newExpectedScope(string $funcName, array $initialCtx = [], array $expectedContextsStackFromCaller = []): array
    {
        $expectedContextsStack = $expectedContextsStackFromCaller;
        $newCount = array_unshift(/* ref */ $expectedContextsStack, new Pair($funcName, $initialCtx));
        self::assertSame(count($expectedContextsStackFromCaller) + 1, $newCount);
        self::assertContextsStack($expectedContextsStack);
        return $expectedContextsStack;
    }

    /**
     * @param Pair<string, array<string, mixed>>[] $expectedContextsStack
     * @param array<string, mixed>                 $ctx
     */
    private static function addToTopExpectedScope(/* ref */ array $expectedContextsStack, array $ctx): void
    {
        self::assertNotEmpty($expectedContextsStack);
        $expectedContextsStack[0]->second = array_merge($expectedContextsStack[0]->second, $ctx);
        self::assertContextsStack($expectedContextsStack);
    }

    /**
     * @return array<string, mixed>
     */
    private static function getTopActualScope(): array
    {
        $actualContextsStack = AssertMessageStack::getContextsStack();
        self::assertNotEmpty($actualContextsStack);
        return ArrayUtilForTests::getFirstValue($actualContextsStack);
    }

    public function testOneFunc(): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, ['my_key' => 1]);
        $expectedContextsStack = self::newExpectedScope(__FUNCTION__, ['my_key' => 1]);
        self::assertSameValueInArray('my_key', 1, self::getTopActualScope());
        $dbgCtx->add(['my_key' => '2']);
        self::addToTopExpectedScope(/* ref */ $expectedContextsStack, ['my_key' => '2']);
        self::assertSameValueInArray('my_key', '2', self::getTopActualScope());
        $dbgCtx->add(['my_other_key' => 3.5]);
        self::addToTopExpectedScope(/* ref */ $expectedContextsStack, ['my_other_key' => 3.5]);
        self::assertSameValueInArray('my_other_key', 3.5, self::getTopActualScope());
    }

    public function testTwoFuncs(): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, ['my context' => 'before func']);
        $expectedContextsStack = self::newExpectedScope(__FUNCTION__, ['my context' => 'before func']);

        /**
         * @param Pair<string, array<string, mixed>>[] $expectedContextsStackFromCaller
         */
        $secondFunc = function (array $expectedContextsStackFromCaller): void {
            AssertMessageStack::newScope(/* out */ $dbgCtx, ['my context' => 'func entry']);
            $expectedContextsStack = self::newExpectedScope(__FUNCTION__, ['my context' => 'func entry'], $expectedContextsStackFromCaller);
            self::assertSameValueInArray('my context', 'func entry', self::getTopActualScope());

            $dbgCtx->add(['some_other_key' => 'inside func']);
            self::addToTopExpectedScope(/* ref */ $expectedContextsStack, ['some_other_key' => 'inside func']);
            self::assertSameValueInArray('some_other_key', 'inside func', self::getTopActualScope());
        };

        $secondFunc($expectedContextsStack);
        self::assertSameValueInArray('my context', 'before func', self::getTopActualScope());
        self::assertArrayNotHasKey('some_other_key', self::getTopActualScope());

        $dbgCtx->add(['my context' => 'after func']);
        self::addToTopExpectedScope(/* ref */ $expectedContextsStack, ['my context' => 'after func']);
        self::assertSameValueInArray('my context', 'after func', self::getTopActualScope());
    }

    public function testSubScopeSimple(): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx);
        $expectedContextsStackOutsideSubScope = self::newExpectedScope(__FUNCTION__);
        $dbgCtx->add(['my context' => 'before sub-scope']);
        self::addToTopExpectedScope(/* ref */ $expectedContextsStackOutsideSubScope, ['my context' => 'before sub-scope']);

        $dbgCtx->pushSubScope();
        {
            $expectedContextsStackInsideSubScope = self::newExpectedScope(__FUNCTION__, /* initialCtx */ [], $expectedContextsStackOutsideSubScope);
            $dbgCtx->add(['my context' => 'inside sub-scope']);
            self::addToTopExpectedScope(/* ref */ $expectedContextsStackInsideSubScope, ['my context' => 'inside sub-scope']);
            self::assertSameValueInArray('my context', 'inside sub-scope', self::getTopActualScope());
        }
        $dbgCtx->popSubScope();
        self::assertContextsStack($expectedContextsStackOutsideSubScope);
        self::assertSameValueInArray('my context', 'before sub-scope', self::getTopActualScope());

        $dbgCtx->add(['my context' => 'after sub-scope']);
        self::addToTopExpectedScope(/* ref */ $expectedContextsStackOutsideSubScope, ['my context' => 'after sub-scope']);
        self::assertSameValueInArray('my context', 'after sub-scope', self::getTopActualScope());
    }

    /**
     * @return iterable<array{bool}>
     */
    public static function boolDataProvider(): iterable
    {
        yield [true];
        yield [false];
    }

    /**
     * @dataProvider boolDataProvider
     */
    public function testSubScopeEarlyReturn(bool $SubScopeShouldExitEarly): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, ['my context' => 'before calling 2nd func']);
        $expectedContextsStack = self::newExpectedScope(__FUNCTION__, ['my context' => 'before calling 2nd func']);
        self::assertSameValueInArray('my context', 'before calling 2nd func', self::getTopActualScope());

        /**
         * @param Pair<string, array<string, mixed>>[] $expectedContextsStackFromCaller
         */
        $secondFunc = function (array $expectedContextsStackFromCaller) use ($SubScopeShouldExitEarly): void {
            AssertMessageStack::newScope(/* out */ $dbgCtx, ['my context' => 'before sub-scope']);
            $expectedContextsStackOutsideSubScope = self::newExpectedScope(__FUNCTION__, ['my context' => 'before sub-scope'], $expectedContextsStackFromCaller);
            self::assertSameValueInArray('my context', 'before sub-scope', self::getTopActualScope());

            $dbgCtx->pushSubScope();
            {
            $expectedContextsStackInsideSubScope = self::newExpectedScope(__FUNCTION__, /* initialCtx */ [], $expectedContextsStackOutsideSubScope);
            $dbgCtx->add(['my context' => 'inside sub-scope']);
            self::addToTopExpectedScope(/* ref */ $expectedContextsStackInsideSubScope, ['my context' => 'inside sub-scope']);
            self::assertSameValueInArray('my context', 'inside sub-scope', self::getTopActualScope());
            if ($SubScopeShouldExitEarly) {
                return;
            }
            }
            $dbgCtx->popSubScope();
            self::assertContextsStack($expectedContextsStackOutsideSubScope);
            self::assertSameValueInArray('my context', 'before sub-scope', self::getTopActualScope());

            $dbgCtx->add(['my context' => 'after sub-scope']);
            self::addToTopExpectedScope(/* ref */ $expectedContextsStackOutsideSubScope, ['my context' => 'after sub-scope']);
            self::assertSameValueInArray('my context', 'after sub-scope', self::getTopActualScope());
        };

        $secondFunc($expectedContextsStack);

        $dbgCtx->add(['my context' => 'after calling 2nd func']);
        self::addToTopExpectedScope(/* ref */ $expectedContextsStack, ['my context' => 'after calling 2nd func']);
        self::assertSameValueInArray('my context', 'after calling 2nd func', self::getTopActualScope());
    }

    public function testSubScopeForLoop(): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx);
        $expectedContextsStackOutsideLoop = self::newExpectedScope(__FUNCTION__);
        $dbgCtx->add(['my context' => 'before loop']);
        self::addToTopExpectedScope(/* ref */ $expectedContextsStackOutsideLoop, ['my context' => 'before loop']);

        $dbgCtx->pushSubScope();
        foreach (RangeUtil::generateUpTo(2) as $index) {
            $dbgCtx->clearCurrentSubScope(['index' => $index]);
            $expectedContextsStackInsideLoop = self::newExpectedScope(__FUNCTION__, /* initialCtx */ ['index' => $index], $expectedContextsStackOutsideLoop);
            self::assertSameValueInArray('index', $index, self::getTopActualScope());
            self::assertSame(1, count(self::getTopActualScope()));
            $dbgCtx->add(['key_with_index_' . $index => 'value_with_index_' . $index]);
            self::addToTopExpectedScope(/* ref */ $expectedContextsStackInsideLoop, ['key_with_index_' . $index => 'value_with_index_' . $index]);
            self::assertSameValueInArray('key_with_index_' . $index, 'value_with_index_' . $index, self::getTopActualScope());
            self::assertSame(2, count(self::getTopActualScope()));
        }
        $dbgCtx->popSubScope();

        $dbgCtx->add(['my context' => 'after loop']);
        self::addToTopExpectedScope(/* ref */ $expectedContextsStackOutsideLoop, ['my context' => 'after loop']);
    }

    public function testSubScopeForLoopWithContinue(): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx);
        $expectedContextsFunc = self::newExpectedScope(__FUNCTION__);
        $dbgCtx->add(['my context' => 'before 1st loop']);
        self::addToTopExpectedScope(/* ref */ $expectedContextsFunc, ['my context' => 'before 1st loop']);

        $dbgCtx->pushSubScope();
        foreach (RangeUtil::generateUpTo(3) as $index1stLoop) {
            $dbgCtx->clearCurrentSubScope(['index1stLoop' => $index1stLoop]);
            $expectedContexts1stLoop = self::newExpectedScope(__FUNCTION__, ['index1stLoop' => $index1stLoop], $expectedContextsFunc);

            $dbgCtx->add(['my context' => 'before 1st loop']);
            self::addToTopExpectedScope(/* ref */ $expectedContexts1stLoop, ['my context' => 'before 1st loop']);

            $dbgCtx->add(['1st loop key with index ' . $index1stLoop => '1st loop value with index ' . $index1stLoop]);
            self::addToTopExpectedScope(/* ref */ $expectedContexts1stLoop, ['1st loop key with index ' . $index1stLoop => '1st loop value with index ' . $index1stLoop]);

            $dbgCtx->pushSubScope();
            foreach (RangeUtil::generateUpTo(5) as $index2ndLoop) {
                $dbgCtx->clearCurrentSubScope(['index2ndLoop' => $index2ndLoop]);
                $expectedContexts2ndLoop = self::newExpectedScope(__FUNCTION__, ['index2ndLoop' => $index2ndLoop], $expectedContexts1stLoop);

                if ($index2ndLoop > 2) {
                    continue;
                }

                $dbgCtx->add(['2nd loop key with index ' . $index2ndLoop => '2nd loop value with index ' . $index2ndLoop]);
                self::addToTopExpectedScope(/* ref */ $expectedContexts2ndLoop, ['2nd loop key with index ' . $index2ndLoop => '2nd loop value with index ' . $index2ndLoop]);
            }
            $dbgCtx->popSubScope();

            if ($index1stLoop > 1) {
                continue;
            }

            $dbgCtx->add(['my context' => 'after 2nd loop']);
            self::addToTopExpectedScope(/* ref */ $expectedContexts1stLoop, ['my context' => 'after 2nd loop']);
        }
        $dbgCtx->popSubScope();

        $dbgCtx->add(['my context' => 'after 1st loop']);
        self::addToTopExpectedScope(/* ref */ $expectedContextsFunc, ['my context' => 'after 1st loop']);
    }

    /**
     * @param int                                  $currentDepth
     * @param Pair<string, array<string, mixed>>[] $expectedContextsStackFromCaller
     */
    private static function recursiveFunc(int $currentDepth, array $expectedContextsStackFromCaller): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, ['my context' => 'inside recursive func before recursive call']);
        $expectedContextsStack = self::newExpectedScope(__FUNCTION__, ['my context' => 'inside recursive func before recursive call'], $expectedContextsStackFromCaller);

        $dbgCtx->add(['key for depth ' . $currentDepth => 'value for depth ' . $currentDepth]);
        self::addToTopExpectedScope(/* ref */ $expectedContextsStack, ['key for depth ' . $currentDepth => 'value for depth ' . $currentDepth]);

        if ($currentDepth < 3) {
            self::recursiveFunc($currentDepth + 1, $expectedContextsStack);
        }

        $assertMsgCtx = ['currentDepth' => $currentDepth, 'expectedContextsStack' => $expectedContextsStack];
        $depth = $currentDepth;
        foreach (AssertMessageStack::getContextsStack() as $actualCtxDesc => $actualCtx) {
            $assertMsgCtxPerIt = array_merge($assertMsgCtx, ['depth' => $depth, 'actualCtxDesc' => $actualCtxDesc, 'actualCtx' => $actualCtx]);
            self::assertStringContainsString(__FUNCTION__, $actualCtxDesc, LoggableToString::convert($assertMsgCtxPerIt));
            self::assertStringContainsString(basename(__FILE__), $actualCtxDesc, LoggableToString::convert($assertMsgCtxPerIt));
            self::assertStringContainsString(__CLASS__, $actualCtxDesc, LoggableToString::convert($assertMsgCtxPerIt));

            self::assertSame('inside recursive func before recursive call', $actualCtx['my context'], LoggableToString::convert($assertMsgCtxPerIt));
            self::assertSame('value for depth ' . $depth, $actualCtx['key for depth ' . $depth], LoggableToString::convert($assertMsgCtxPerIt));

            if ($depth === 1) {
                break;
            }
            --$depth;
        }

        $dbgCtx->add(['my context' => 'inside recursive func after recursive call']);
        self::addToTopExpectedScope(/* ref */ $expectedContextsStack, ['my context' => 'inside recursive func after recursive call']);
    }

    public function testRecursiveFunc(): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, ['my context' => 'before recursive func']);
        $expectedContextsStack = self::newExpectedScope(__FUNCTION__, ['my context' => 'before recursive func']);

        self::recursiveFunc(1, $expectedContextsStack);
    }

    /**
     * @param int     $intParam
     * @param ?string $nullableStringParam
     *
     * @return array<string, mixed>
     *
     * @noinspection PhpUnusedParameterInspection
     */
    private static function helperFuncForTestFuncArgs(int $intParam, ?string $nullableStringParam): array
    {
        return AssertMessageStack::funcArgs();
    }

    /**
     * @return iterable<array{int, ?string, array<string, mixed>}>
     */
    public static function dataProviderForTestFuncArgs(): iterable
    {
        yield [1, 'abc', ['intParam' => 1, 'nullableStringParam' => 'abc']];
        yield [2, null, ['intParam' => 2, 'nullableStringParam' => null]];
    }

    /**
     * @dataProvider dataProviderForTestFuncArgs
     *
     * @param int                  $intParam
     * @param ?string              $nullableStringParam
     * @param array<string, mixed> $expectedArgs
     */
    public function testFuncArgs(int $intParam, ?string $nullableStringParam, array $expectedArgs): void
    {
        $actualArgs = self::helperFuncForTestFuncArgs($intParam, $nullableStringParam);
        $dbgCtx = ['intParam' => $intParam, 'nullableStringParam' => $nullableStringParam, 'expectedArgs' => $expectedArgs, 'actualArgs' => $actualArgs];
        self::assertSame(count($expectedArgs), count($actualArgs), LoggableToString::convert($dbgCtx));
        foreach (IterableUtilForTests::zip(array_keys($expectedArgs), array_keys($actualArgs)) as [$expectedParamName, $actualParamName]) {
            $dbgCtxPerArg = array_merge($dbgCtx, ['expectedParamName' => $expectedParamName, 'actualParamName' => $actualParamName]);
            self::assertSame($expectedParamName, $actualParamName, LoggableToString::convert($dbgCtxPerArg));
            self::assertSame($expectedArgs[$expectedParamName], $actualArgs[$actualParamName], LoggableToString::convert($dbgCtxPerArg));
        }
    }

    public function testCaptureVarByRef(): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx);

        $localVar = 1;
        $dbgCtx->add(['localVar' => &$localVar]);

        $localVar = 2;

        $capturedCtxStack = AssertMessageStack::getContextsStack();
        self::assertCount(1, $capturedCtxStack);
        $thisFuncCtx = ArrayUtilForTests::getFirstValue($capturedCtxStack);
        self::assertArrayHasKey('localVar', $thisFuncCtx);
        self::assertSame(2, $thisFuncCtx['localVar']);
    }
}
