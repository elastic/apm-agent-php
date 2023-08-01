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

use Elastic\Apm\Impl\StackTraceFrame;
use Elastic\Apm\Impl\Util\ArrayUtil;
use Elastic\Apm\Impl\Util\ClassicFormatStackTraceFrame;
use Elastic\Apm\Impl\Util\RangeUtil;
use Elastic\Apm\Impl\Util\StackTraceUtil;
use ElasticApmTests\ComponentTests\Util\AmbientContextForTests;
use ElasticApmTests\UnitTests\Util\SourceCodeLocation;
use ElasticApmTests\Util\ArrayUtilForTests;
use ElasticApmTests\Util\AssertMessageStack;
use ElasticApmTests\Util\DataProviderForTestBuilder;
use ElasticApmTests\Util\DummyExceptionForTests;
use ElasticApmTests\Util\MixedMap;
use ElasticApmTests\Util\StackTraceExpectations;
use ElasticApmTests\Util\StackTraceFrameExpectations;
use ElasticApmTests\Util\TestCaseBase;

use function count;

class StackTraceUtilTest extends TestCaseBase
{
    private const NUMBER_OF_STACK_FRAMES_TO_SKIP_KEY = 'number_of_stack_frames_to_skip';
    private const MAX_NUMBER_OF_FRAMES_KEY = 'max_number_of_frames';
    private const KEEP_ELASTIC_APM_FRAMES_KEY = 'keep_elastic_apm_frames';
    private const INCLUDE_ARGS_KEY = 'include_args';
    private const INCLUDE_THIS_OBJ_KEY = 'include_this_obj';

    private const INPUT_KEY = 'input';
    private const FULL_EXPECTED_OUTPUT_KEY = 'full_expected_output';

    private const VERY_LARGE_STACK_TRACE_SIZE_LIMIT = 1000;

    /** @var ?StackTraceUtil */
    private static $stackTraceUtil = null;

    private static function stackTraceUtil(): StackTraceUtil
    {
        if (self::$stackTraceUtil === null) {
            self::$stackTraceUtil = new StackTraceUtil(AmbientContextForTests::loggerFactory());
        }

        return self::$stackTraceUtil;
    }

    public function testClosureExpections(): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx);

        $closureFrameExpections = StackTraceFrameExpectations::fromLocationOnly(__FILE__, /* temp dummy lineNumber */ 0);
        /**
         * @return StackTraceFrame[]
         */
        $closure = function () use ($closureFrameExpections): array {
            $closureFrameExpections->lineno->setValue(__LINE__ + 1);
            return self::stackTraceUtil()->captureInApmFormat(/* offset */ 0, /* maxNumberOfFrames */ null);
        };
        $thisFuncFrameExpections = StackTraceFrameExpectations::fromClosure(__FILE__, __LINE__ + 1, __NAMESPACE__, __CLASS__, /* isStatic */ false);
        $actualStackTrace = $closure();
        $dbgCtx->add(compact('actualStackTrace'));
        self::assertCountAtLeast(3, $actualStackTrace);
        $closureFrameExpections->assertMatches($actualStackTrace[0]);
        self::assertNull($actualStackTrace[0]->function);
        $thisFuncFrameExpections->assertMatches($actualStackTrace[1]);
    }

    public static function testStaticClosureExpections(): void
    {
        $closureFrameExpections = StackTraceFrameExpectations::fromLocationOnly(__FILE__, /* temp dummy lineNumber */ 0);
        /**
         * @return StackTraceFrame[]
         */
        $closure = function () use ($closureFrameExpections): array {
            $closureFrameExpections->lineno->setValue(__LINE__ + 1);
            return self::stackTraceUtil()->captureInApmFormat(/* offset */ 0, /* maxNumberOfFrames */ null);
        };
        $thisFuncFrameExpections = StackTraceFrameExpectations::fromClosure(__FILE__, __LINE__ + 1, __NAMESPACE__, __CLASS__, /* isStatic */ true);
        $actualStackTrace = $closure();
        self::assertCountAtLeast(3, $actualStackTrace);
        $closureFrameExpections->assertMatches($actualStackTrace[0]);
        self::assertNull($actualStackTrace[0]->function);
        $thisFuncFrameExpections->assertMatches($actualStackTrace[1]);
    }

    /**
     * @return iterable<array{?string, ?bool, ?string, ?string}>
     */
    public function dataProviderForTestConvertClassAndMethodToFunctionName(): iterable
    {
        yield ['MyClass', /* isStaticMethod */ true, 'myMethod', 'MyClass::myMethod'];
        yield ['MyClass', /* isStaticMethod */ false, 'myMethod', 'MyClass->myMethod'];
        yield ['MyClass', /* isStaticMethod */ null, 'myMethod', 'MyClass.myMethod'];

        yield ['MyNamespace\\MyClass', /* isStaticMethod */ false, 'myMethod', 'MyNamespace\\MyClass->myMethod'];
        yield ['', /* isStaticMethod */ false, 'myMethod', '->myMethod'];
        yield ['', /* isStaticMethod */ true, 'myMethod', '::myMethod'];
        yield ['', /* isStaticMethod */ null, 'myMethod', '.myMethod'];
        yield [null, /* isStaticMethod */ true, 'myMethod', 'myMethod'];
        yield [null, /* isStaticMethod */ false, 'myMethod', 'myMethod'];
        yield [null, /* isStaticMethod */ null, 'myMethod', 'myMethod'];

        yield ['MyClass', /* isStaticMethod */ false, '', 'MyClass->'];
        yield ['MyClass', /* isStaticMethod */ true, '', 'MyClass::'];
        yield ['MyClass', /* isStaticMethod */ true, null, null];
        yield ['MyClass', /* isStaticMethod */ false, null, null];

        yield [null, /* isStaticMethod */ true, null, null];
        yield [null, /* isStaticMethod */ false, null, null];
        yield [null, /* isStaticMethod */ null, null, null];
    }

    /**
     * @dataProvider dataProviderForTestConvertClassAndMethodToFunctionName
     */
    public function testConvertClassAndMethodToFunctionName(?string $classicName, ?bool $isStaticMethod, ?string $methodName, ?string $expectedFuncName): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, AssertMessageStack::funcArgs());

        $actualFuncName = StackTraceUtil::buildApmFormatFunctionForClassMethod($classicName, $isStaticMethod, $methodName);
        $dbgCtx->add(['actualFuncName' => $actualFuncName]);

        self::assertSame($expectedFuncName, $actualFuncName);
    }

    /**
     * @return iterable<string, array{MixedMap}>
     */
    public function dataProviderForTestCaptureInApmFormat(): iterable
    {
        $result = (new DataProviderForTestBuilder())
            ->addKeyedDimensionAllValuesCombinable(self::NUMBER_OF_STACK_FRAMES_TO_SKIP_KEY, [0, 1, 2, 3, 4, 5, 10, self::VERY_LARGE_STACK_TRACE_SIZE_LIMIT])
            ->addKeyedDimensionAllValuesCombinable(self::MAX_NUMBER_OF_FRAMES_KEY, [null, 0, 1, 2, 3, 4, 5, 10, self::VERY_LARGE_STACK_TRACE_SIZE_LIMIT])
            ->build();

        return DataProviderForTestBuilder::convertEachDataSetToMixedMap($result);
    }

    /**
     * @dataProvider dataProviderForTestCaptureInApmFormat
     */
    public function testCaptureInApmFormatOneTestFame(MixedMap $testArgs): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, AssertMessageStack::funcArgs());

        $numberOfFramesToSkip = $testArgs->getPositiveOrZeroInt(self::NUMBER_OF_STACK_FRAMES_TO_SKIP_KEY);
        $maxNumberOfFrames = $testArgs->getNullablePositiveOrZeroInt(self::MAX_NUMBER_OF_FRAMES_KEY);

        $phpFormatStackTrace = debug_backtrace();
        $dbgCtx->add(['phpFormatStackTrace' => $phpFormatStackTrace]);
        $lineCaptureCall = __LINE__ + 1;
        $actualCapturedStackTrace = $maxNumberOfFrames === 0 ? [] : self::stackTraceUtil()->captureInApmFormat($numberOfFramesToSkip, $maxNumberOfFrames);
        $dbgCtx->add(['actualCapturedStackTrace' => $actualCapturedStackTrace]);

        if ($maxNumberOfFrames === 0 || $numberOfFramesToSkip >= count($phpFormatStackTrace)) {
            self::assertEmpty($actualCapturedStackTrace);
        } else {
            self::assertNotEmpty($actualCapturedStackTrace);
        }

        $shouldHaveFrame = function (int $fullStackFrameIndex) use ($numberOfFramesToSkip, $maxNumberOfFrames): bool {
            return $numberOfFramesToSkip <= $fullStackFrameIndex && ($maxNumberOfFrames === null || $maxNumberOfFrames > ($fullStackFrameIndex - $numberOfFramesToSkip));
        };

        $actualCapturedStackTraceFrameIndex = 0;
        $dbgCtx->add(['actualCapturedStackTraceFrameIndex' => &$actualCapturedStackTraceFrameIndex]);
        $fullStackFrameIndex = 0;
        $dbgCtx->add(['fullStackFrameIndex' => &$fullStackFrameIndex]);

        if ($shouldHaveFrame($fullStackFrameIndex)) {
            $frame = $actualCapturedStackTrace[$actualCapturedStackTraceFrameIndex];
            self::assertSame(__FILE__, $frame->filename);
            self::assertSame($lineCaptureCall, $frame->lineno);
            ++$actualCapturedStackTraceFrameIndex;
        }

        ++$fullStackFrameIndex;
        if ($shouldHaveFrame($fullStackFrameIndex)) {
            $frame = $actualCapturedStackTrace[$actualCapturedStackTraceFrameIndex];
            self::assertSame(StackTraceUtil::buildApmFormatFunctionForClassMethod(__CLASS__, /* isStatic */ false, __FUNCTION__), $frame->function);
            self::assertNotNull($frame->function);
            self::assertStringContainsString(__FUNCTION__, $frame->function);
        } elseif ($actualCapturedStackTraceFrameIndex <= count($actualCapturedStackTrace) - 1) {
            $frame = $actualCapturedStackTrace[$actualCapturedStackTraceFrameIndex];
            self::assertNotNull($frame->function);
            self::assertStringNotContainsString(__FUNCTION__, $frame->function);
        }
    }

    /**
     * @param MixedMap                $testArgs
     * @param array<string, mixed>[] &$expectedFramesProps
     * @param array<string, mixed>   &$dbgStackTrace
     *
     * @return StackTraceFrame[]
     *
     * @param-out array<string, mixed> $dbgStackTrace
     */
    private function helper1ForTestCaptureInApmFormatMultipleTestFrames(MixedMap $testArgs, array &$expectedFramesProps, ?array &$dbgStackTrace): array
    {
        array_unshift(/* ref */ $expectedFramesProps, [StackTraceUtil::LINE_KEY => __LINE__ + 1]);
        return self::helper2StaticForTestCaptureInApmFormatMultipleTestFrames($testArgs, $expectedFramesProps, $dbgStackTrace);
    }

    /**
     * @param MixedMap                $testArgs
     * @param array<string, mixed>[] &$expectedFramesProps
     * @param array<string, mixed>   &$dbgStackTrace
     *
     * @return StackTraceFrame[]
     *
     * @param-out array<string, mixed> $dbgStackTrace
     */
    private static function helper2StaticForTestCaptureInApmFormatMultipleTestFrames(MixedMap $testArgs, array &$expectedFramesProps, ?array &$dbgStackTrace): array
    {
        $func = function () use ($testArgs, &$expectedFramesProps, &$dbgStackTrace): array {
            array_unshift(/* ref */ $expectedFramesProps, [StackTraceUtil::LINE_KEY => DUMMY_FUNC_FOR_TESTS_WITHOUT_NAMESPACE_CALLABLE_LINE_NUMBER]);
            $numberOfFramesToSkip = $testArgs->getPositiveOrZeroInt(self::NUMBER_OF_STACK_FRAMES_TO_SKIP_KEY);
            $maxNumberOfFrames = $testArgs->getNullablePositiveOrZeroInt(self::MAX_NUMBER_OF_FRAMES_KEY);
            $dbgStackTrace = debug_backtrace();
            array_unshift(/* ref */ $expectedFramesProps, [StackTraceUtil::LINE_KEY => __LINE__ + 1, StackTraceUtil::FUNCTION_KEY => __FUNCTION__]);
            return $maxNumberOfFrames === 0 ? [] : self::stackTraceUtil()->captureInApmFormat($numberOfFramesToSkip, $maxNumberOfFrames);
        };

        array_unshift(/* ref */ $expectedFramesProps, [StackTraceUtil::LINE_KEY => __LINE__ + 1]);
        return dummyFuncForTestsWithoutNamespace($func);
    }

    /**
     * @dataProvider dataProviderForTestCaptureInApmFormat
     */
    public function testCaptureInApmFormatMultipleTestFrames(MixedMap $testArgs): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, AssertMessageStack::funcArgs());

        $numberOfFramesToSkip = $testArgs->getPositiveOrZeroInt(self::NUMBER_OF_STACK_FRAMES_TO_SKIP_KEY);
        $maxNumberOfFrames = $testArgs->getNullablePositiveOrZeroInt(self::MAX_NUMBER_OF_FRAMES_KEY);

        /** @var array<string, mixed>[] $expectedFramesProps */
        $expectedFramesProps = [];
        array_unshift(/* ref */ $expectedFramesProps, [StackTraceUtil::LINE_KEY => __LINE__ + 1]);
        $actualCapturedStackTrace = self::helper1ForTestCaptureInApmFormatMultipleTestFrames($testArgs, /* out */ $expectedFramesProps, /* out */ $dbgStackTrace);
        $dbgCtx->add(['actualCapturedStackTrace' => $actualCapturedStackTrace, 'expectedFramesProps' => $expectedFramesProps, 'dbgStackTrace' => $dbgStackTrace]);

        if ($maxNumberOfFrames === 0 || $numberOfFramesToSkip >= count($dbgStackTrace)) {
            self::assertEmpty($actualCapturedStackTrace);
        } else {
            self::assertNotEmpty($actualCapturedStackTrace);
        }

        $shouldHaveFrame = function (int $fullStackFrameIndex) use ($numberOfFramesToSkip, $maxNumberOfFrames): bool {
            return $numberOfFramesToSkip <= $fullStackFrameIndex && ($maxNumberOfFrames === null || $maxNumberOfFrames > ($fullStackFrameIndex - $numberOfFramesToSkip));
        };

        $actualCapturedStackTraceFrameIndex = 0;
        $dbgCtx->add(['actualCapturedStackTraceFrameIndex' => &$actualCapturedStackTraceFrameIndex]);
        $fullStackFrameIndex = 0;
        $dbgCtx->add(['fullStackFrameIndex' => &$fullStackFrameIndex]);

        if ($shouldHaveFrame($fullStackFrameIndex)) {
            $frame = $actualCapturedStackTrace[$actualCapturedStackTraceFrameIndex];
            self::assertSame(__FILE__, $frame->filename);
            self::assertSame($expectedFramesProps[$fullStackFrameIndex][StackTraceUtil::LINE_KEY], $frame->lineno);
            ++$actualCapturedStackTraceFrameIndex;
        }

        ++$fullStackFrameIndex;
        if ($shouldHaveFrame($fullStackFrameIndex)) {
            $frame = $actualCapturedStackTrace[$actualCapturedStackTraceFrameIndex];
            $apmFormatFunction = StackTraceUtil::buildApmFormatFunctionForClassMethod(__CLASS__, /* isStatic */ true, $expectedFramesProps[$fullStackFrameIndex - 1][StackTraceUtil::FUNCTION_KEY]);
            self::assertSame($apmFormatFunction, $frame->function);
            self::assertSame(DUMMY_FUNC_FOR_TESTS_WITHOUT_NAMESPACE_CALLABLE_FILE_NAME, $frame->filename);
            self::assertSame($expectedFramesProps[$fullStackFrameIndex][StackTraceUtil::LINE_KEY], $frame->lineno);
            ++$actualCapturedStackTraceFrameIndex;
        }

        ++$fullStackFrameIndex;
        if ($shouldHaveFrame($fullStackFrameIndex)) {
            $frame = $actualCapturedStackTrace[$actualCapturedStackTraceFrameIndex];
            self::assertSame(StackTraceUtil::buildApmFormatFunctionForClassMethod(/* class */ null, /* isStaticMethod */ null, 'dummyFuncForTestsWithoutNamespace'), $frame->function);
            self::assertSame(__FILE__, $frame->filename);
            self::assertSame($expectedFramesProps[$fullStackFrameIndex][StackTraceUtil::LINE_KEY], $frame->lineno);
            ++$actualCapturedStackTraceFrameIndex;
        }

        ++$fullStackFrameIndex;
        if ($shouldHaveFrame($fullStackFrameIndex)) {
            $frame = $actualCapturedStackTrace[$actualCapturedStackTraceFrameIndex];
            $apmFormatFunction = StackTraceUtil::buildApmFormatFunctionForClassMethod(__CLASS__, /* isStaticMethod */ true, 'helper2StaticForTestCaptureInApmFormatMultipleTestFrames');
            self::assertSame($apmFormatFunction, $frame->function);
            self::assertSame(__FILE__, $frame->filename);
            self::assertSame($expectedFramesProps[$fullStackFrameIndex][StackTraceUtil::LINE_KEY], $frame->lineno);
            ++$actualCapturedStackTraceFrameIndex;
        }

        ++$fullStackFrameIndex;
        if ($shouldHaveFrame($fullStackFrameIndex)) {
            $frame = $actualCapturedStackTrace[$actualCapturedStackTraceFrameIndex];
            $apmFormatFunction = StackTraceUtil::buildApmFormatFunctionForClassMethod(__CLASS__, /* isStaticMethod */ false, 'helper1ForTestCaptureInApmFormatMultipleTestFrames');
            self::assertSame($apmFormatFunction, $frame->function);
            self::assertSame(__FILE__, $frame->filename);
            self::assertSame($expectedFramesProps[$fullStackFrameIndex][StackTraceUtil::LINE_KEY], $frame->lineno);
            ++$actualCapturedStackTraceFrameIndex;
        }

        ++$fullStackFrameIndex;
        if ($shouldHaveFrame($fullStackFrameIndex)) {
            $frame = $actualCapturedStackTrace[$actualCapturedStackTraceFrameIndex];
            self::assertSame(StackTraceUtil::buildApmFormatFunctionForClassMethod(__CLASS__, /* isStaticMethod */ false, __FUNCTION__), $frame->function);
            self::assertNotNull($frame->function);
            self::assertStringContainsString(__FUNCTION__, $frame->function);
        } elseif ($actualCapturedStackTraceFrameIndex <= count($actualCapturedStackTrace) - 1) {
            $frame = $actualCapturedStackTrace[$actualCapturedStackTraceFrameIndex];
            self::assertNotNull($frame->function);
            self::assertStringNotContainsString(__FUNCTION__, $frame->function);
        }
    }

    /**
     * @param ?string $file
     * @param ?int    $line
     * @param ?string $class
     * @param ?bool   $isStaticMethod
     * @param ?string $method
     *
     * @return array<string, mixed>
     */
    private static function buildInputPhpFormatFrame(?string $file, ?int $line, ?string $class, ?bool $isStaticMethod, ?string $method): array
    {
        $result = [];
        $addToResult = function (string $key, $val) use (&$result): void {
            if ($val !== null) {
                $result[$key] = $val;
            }
        };

        $addToResult(StackTraceUtil::FILE_KEY, $file);
        $addToResult(StackTraceUtil::LINE_KEY, $line);
        $addToResult(StackTraceUtil::CLASS_KEY, $class);
        $addToResult(StackTraceUtil::TYPE_KEY, $isStaticMethod === null ? null : ($isStaticMethod ? '::' : '->'));
        $addToResult(StackTraceUtil::FUNCTION_KEY, $method);

        return $result;
    }

    /**
     * @return iterable<array{iterable<array<string, mixed>>, StackTraceFrameExpectations[]}>
     */
    private static function dataProviderForTestConvertPhpToApmFormatDataSets(): iterable
    {
        yield [
            [
                self::buildInputPhpFormatFrame('app_bootstrap.php', 1, 'AppClass', /* isStaticMethod */ false, 'myMethod'),
            ],
            [
                StackTraceFrameExpectations::fromClassMethod('app_bootstrap.php', 1, 'AppClass', /* isStaticMethod */ false, 'myMethod'),
            ]
        ];

        yield [
            [
                self::buildInputPhpFormatFrame('AppClass.php', 1, 'Elastic\\Apm\\ElasticApm', /* isStaticMethod */ true, 'beginTransaction'),
                self::buildInputPhpFormatFrame('app_bootstrap.php', 2, 'AppClass', /* isStaticMethod */ false, 'myMethod'),
            ],
            [
                StackTraceFrameExpectations::fromLocationOnly('AppClass.php', 1),
                StackTraceFrameExpectations::fromClassMethod('app_bootstrap.php', 2, 'AppClass', /* isStaticMethod */ false, 'myMethod'),
            ]
        ];

        yield [
            [
                self::buildInputPhpFormatFrame('Span.php', 1, 'Elastic\\Apm\\Impl\\StackTraceUtil', /* isStaticMethod */ true, 'capture'),
                self::buildInputPhpFormatFrame('AppClass.php', 2, 'Elastic\\Apm\\Impl\\Span', /* isStaticMethod */ false, 'end'),
                self::buildInputPhpFormatFrame('app_bootstrap.php', 3, 'AppClass', /* isStaticMethod */ false, 'myMethod'),
            ],
            [
                StackTraceFrameExpectations::fromLocationOnly('AppClass.php', 2),
                StackTraceFrameExpectations::fromClassMethod('app_bootstrap.php', 3, 'AppClass', /* isStaticMethod */ false, 'myMethod'),
            ]
        ];

        yield [
            [
                self::buildInputPhpFormatFrame('Span.php', 17, 'Elastic\\Apm\\Impl\\StackTraceUtil', /* isStaticMethod */ true, 'capture'),
                self::buildInputPhpFormatFrame('WordPressFilterCallbackWrapper.php', 16, 'Elastic\\Apm\\Impl\\Span', false, 'endSpanEx'),
                self::buildInputPhpFormatFrame(null, null, 'Elastic\\Apm\\Impl\\AutoInstrument\\WordPressFilterCallbackWrapper', false, '__invoke'),
                self::buildInputPhpFormatFrame('class-wp-hook.php', 14, null, /* isStaticMethod */ null, 'call_user_func_array'),
                self::buildInputPhpFormatFrame('plugin.php', 13, 'WP_Hook', /* isStaticMethod */ false, 'apply_filters'),
                self::buildInputPhpFormatFrame('my_pugin.php', 12, null, /* isStaticMethod */ null, 'apply_filters'),
                self::buildInputPhpFormatFrame('WordPressFilterCallbackWrapper.php', 11, null, /* isStaticMethod */ null, 'myPluginFilterCallback'),
                self::buildInputPhpFormatFrame('class-wp-hook.php', 10, 'Elastic\\Apm\\Impl\\AutoInstrument\\WordPressFilterCallbackWrapper', false, '__invoke'),
                self::buildInputPhpFormatFrame('plugin.php', 9, 'WP_Hook', /* isStaticMethod */ false, 'do_action'),
                self::buildInputPhpFormatFrame('my_theme.php', 8, null, /* isStaticMethod */ null, 'do_action'),
                self::buildInputPhpFormatFrame(null, null, 'MyTheme', /* isStaticMethod */ false, 'filterCallback'),
                self::buildInputPhpFormatFrame('WordPressFilterCallbackWrapper.php', 6, null, /* isStaticMethod */ null, 'call_user_func'),
                self::buildInputPhpFormatFrame(null, null, 'Elastic\\Apm\\Impl\\AutoInstrument\\WordPressFilterCallbackWrapper', false, '__invoke'),
                self::buildInputPhpFormatFrame('class-wp-hook.php', 4, null, /* isStaticMethod */ null, 'call_user_func_array'),
                self::buildInputPhpFormatFrame('plugin.php', 3, 'WP_Hook', /* isStaticMethod */ false, 'apply_filters'),
                self::buildInputPhpFormatFrame('option.php', 2, null, /* isStaticMethod */ null, 'apply_filters'),
                self::buildInputPhpFormatFrame('index.php', 1, null, /* isStaticMethod */ null, 'require'),
            ],
            [
                StackTraceFrameExpectations::fromStandaloneFunction('class-wp-hook.php', 14, 'call_user_func_array'),
                StackTraceFrameExpectations::fromClassMethod('plugin.php', 13, 'WP_Hook', /* isStaticMethod */ false, 'apply_filters'),
                StackTraceFrameExpectations::fromStandaloneFunction('my_pugin.php', 12, 'apply_filters'),
                StackTraceFrameExpectations::fromStandaloneFunction('class-wp-hook.php', 10, 'myPluginFilterCallback'),
                StackTraceFrameExpectations::fromClassMethod('plugin.php', 9, 'WP_Hook', /* isStaticMethod */ false, 'do_action'),
                StackTraceFrameExpectations::fromStandaloneFunction('my_theme.php', 8, 'do_action'),
                StackTraceFrameExpectations::fromClassMethodNoLocation('MyTheme', /* isStaticMethod */ false, 'filterCallback'),
                StackTraceFrameExpectations::fromStandaloneFunction('class-wp-hook.php', 4, 'call_user_func_array'),
                StackTraceFrameExpectations::fromClassMethod('plugin.php', 3, 'WP_Hook', /* isStaticMethod */ false, 'apply_filters'),
                StackTraceFrameExpectations::fromStandaloneFunction('option.php', 2, 'apply_filters'),
                StackTraceFrameExpectations::fromStandaloneFunction('index.php', 1, 'require'),
            ]
        ];

        yield [[], []];

        $effectivelyNoopInputFrame = self::buildInputPhpFormatFrame(null, null, __CLASS__, null, null);
        foreach (RangeUtil::generateUpTo(3) as $emptyInputFrameCounts) {
            $inputFrames = [];
            foreach (RangeUtil::generateUpTo($emptyInputFrameCounts) as $ignored) {
                $inputFrames[] = $effectivelyNoopInputFrame;
            }
            yield [$inputFrames, []];
        }
    }

    /**
     * @return iterable<string, array{MixedMap}>
     */
    public function dataProviderForTestConvertPhpToApmFormat(): iterable
    {
        /**
         * @return iterable<array<string, mixed>>
         */
        $genDataSets = function (): iterable {
            foreach (self::dataProviderForTestConvertPhpToApmFormatDataSets() as [$input, $fullExpectedOutput]) {
                $result = [self::INPUT_KEY => $input, self::FULL_EXPECTED_OUTPUT_KEY => $fullExpectedOutput];
                $maxNumberOfFramesVariants = [null, 1, 2, 3, 4, 5, 10, self::VERY_LARGE_STACK_TRACE_SIZE_LIMIT];
                $fullExpectedOutputCount = count($fullExpectedOutput);
                if ($fullExpectedOutputCount !== 0) {
                    ArrayUtilForTests::addToListIfNotAlreadyPresent($fullExpectedOutputCount - 1, /* ref */ $maxNumberOfFramesVariants);
                }
                ArrayUtilForTests::addToListIfNotAlreadyPresent($fullExpectedOutputCount, /* ref */ $maxNumberOfFramesVariants);
                ArrayUtilForTests::addToListIfNotAlreadyPresent($fullExpectedOutputCount + 1, /* ref */ $maxNumberOfFramesVariants);
                foreach ($maxNumberOfFramesVariants as $maxNumberOfFrames) {
                    $result[self::MAX_NUMBER_OF_FRAMES_KEY] = $maxNumberOfFrames;
                    yield $result;
                }
            }
        };

        return DataProviderForTestBuilder::convertEachDataSetToMixedMapAndAddDesc($genDataSets);
    }

    /**
     * @dataProvider dataProviderForTestConvertPhpToApmFormat
     */
    public function testConvertPhpToApmFormat(MixedMap $testArgs): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, AssertMessageStack::funcArgs());

        $maxNumberOfFrames = $testArgs->getNullablePositiveOrZeroInt(self::MAX_NUMBER_OF_FRAMES_KEY);
        $inputFrames = $testArgs->getArray(self::INPUT_KEY);
        /** @var iterable<array<string, mixed>> $inputFrames */
        $fullExpectedOutput = $testArgs->getArray(self::FULL_EXPECTED_OUTPUT_KEY);
        /** @var StackTraceFrameExpectations[] $fullExpectedOutput */
        $actualOutputFrames = $maxNumberOfFrames === 0 ? [] : self::stackTraceUtil()->convertPhpToApmFormat($inputFrames, $maxNumberOfFrames);
        $dbgCtx->add(['actualOutputFrames' => $actualOutputFrames]);
        $expectedOutput = $maxNumberOfFrames === null ? $fullExpectedOutput : array_slice($fullExpectedOutput, /* offset */ 0, /* length */ $maxNumberOfFrames);
        StackTraceExpectations::fromFramesExpectations($expectedOutput)->assertMatches($actualOutputFrames);
    }

    private const CALL_KINDS_SEQUENCE_KEY = 'call_kinds_sequence';

    /**
     * @return iterable<string, array{MixedMap}>
     */
    public function dataProviderForTestCaptureInApmFormatOnDummyCode(): iterable
    {
        $appCode = new StackTraceUtilTestDummyAppCode();
        $codeToHide = new StackTraceUtilTestDummyCodeToHide();

        $codeToHideCallKinds = $codeToHide->callKinds();
        $callKindsCount = count($codeToHideCallKinds);
        $appCodeCallKinds = $appCode->callKinds();
        self::assertCount($callKindsCount, $appCodeCallKinds);

        /**
         * @return iterable<StackTraceUtilTestDummyCodeCallKind[]>
         */
        $genEveryCallKindInRowSequences = function () use ($codeToHideCallKinds, $appCodeCallKinds, $callKindsCount): iterable {
            foreach (RangeUtil::generateFromToIncluding(1, 3) as $inARowLen) {
                foreach ([true, false] as $isBottomFrameAppCode) {
                    foreach (RangeUtil::generateUpTo($callKindsCount) as $bottomFrameCallKindIndex) {
                        $result = [];
                        foreach (RangeUtil::generateUpTo($callKindsCount) as $callKindIndex) {
                            foreach (($isBottomFrameAppCode ? [true, false] : [false, true]) as $isFromAppCode) {
                                $callKinds = $isFromAppCode ? $appCodeCallKinds : $codeToHideCallKinds;
                                foreach (RangeUtil::generateUpTo($inARowLen) as $ignored) {
                                    $result[] = $callKinds[($bottomFrameCallKindIndex + $callKindIndex) % $callKindsCount];
                                }
                            }
                        }
                        yield $result;
                    }
                }
            }
        };

        /**
         * @param StackTraceUtilTestDummyCodeCallKind[]  $src
         * @param int                                    $offset
         * @param int                                    $countToAppend
         * @param StackTraceUtilTestDummyCodeCallKind[] &$result
         *
         * @return void
         */
        $appendElementsFrom = function (array $src, int &$offset, int $countToAppend, array &$result): void {
            foreach (RangeUtil::generateUpTo($countToAppend) as $ignored) {
                $result[] = $src[$offset];
                $offset = ($offset + 1) % count($src);
            }
        };

        /**
         * @return iterable<StackTraceUtilTestDummyCodeCallKind[]>
         */
        $genSequencesForTrampolineSpecialCases = function () use ($codeToHide, $appCode, $appendElementsFrom): iterable {
            $appCodeDirectCallKindsNextIndex = 0;
            $appCodeTrampolineCallKindsNextIndex = 0;
            $codeToHideDirectCallKindsNextIndex = 0;
            $codeToHideTrampolineCallKindsNextIndex = 0;
            foreach (RangeUtil::generateFromToIncluding(1, 3) as $midTrampSeqLen) {
                foreach ([true, false] as $isMidTrampSeqFromAppCode) {
                    foreach (RangeUtil::generateFromToIncluding(1, 3) as $beforeSeqLen) {
                        foreach (RangeUtil::generateFromToIncluding(1, 3) as $afterSeqLen) {
                            $result = [];
                            if ($isMidTrampSeqFromAppCode) {
                                $trampolineCallKinds = $appCode->trampolineCallKinds();
                                $trampolineCallKindsNextIndex =& $appCodeTrampolineCallKindsNextIndex;
                                $directCallKinds = $codeToHide->directCallKinds();
                                $directCallKindsNextIndex =& $codeToHideDirectCallKindsNextIndex;
                            } else {
                                $trampolineCallKinds = $codeToHide->trampolineCallKinds();
                                $trampolineCallKindsNextIndex =& $codeToHideTrampolineCallKindsNextIndex;
                                $directCallKinds = $appCode->directCallKinds();
                                $directCallKindsNextIndex =& $appCodeDirectCallKindsNextIndex;
                            }
                            $appendElementsFrom($directCallKinds, /* ref */ $directCallKindsNextIndex, $beforeSeqLen, /* ref */ $result);
                            $appendElementsFrom($trampolineCallKinds, /* ref */ $trampolineCallKindsNextIndex, $midTrampSeqLen, /* ref */ $result);
                            $appendElementsFrom($directCallKinds, /* ref */ $directCallKindsNextIndex, $afterSeqLen, /* ref */ $result);
                            yield $result;
                        }
                    }
                }
            }
        };

        $genCallKindsSequences = function () use ($genEveryCallKindInRowSequences, $genSequencesForTrampolineSpecialCases): iterable {
            yield from $genEveryCallKindInRowSequences();
            yield from $genSequencesForTrampolineSpecialCases();
        };

        $result = (new DataProviderForTestBuilder())
            ->addKeyedDimensionAllValuesCombinable(self::CALL_KINDS_SEQUENCE_KEY, $genCallKindsSequences)
            ->build();

        return DataProviderForTestBuilder::convertEachDataSetToMixedMap($result);
    }

    /**
     * @dataProvider dataProviderForTestCaptureInApmFormatOnDummyCode
     */
    public function testCaptureInApmFormatOnDummyCode(MixedMap $testArgs): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, AssertMessageStack::funcArgs());

        /** @var StackTraceUtilTestDummyCodeCallKind[] $callKindsSeq */
        $callKindsSeq = $testArgs->getArray(self::CALL_KINDS_SEQUENCE_KEY);
        self::assertNotEmpty($callKindsSeq);

        $stackTraceUtil = new StackTraceUtil(AmbientContextForTests::loggerFactory(), /* namePrefixForFramesToHide */ StackTraceUtilTestDummyCodeToHide::NAME_PREFIX);
        $firstCall = $callKindsSeq[0];
        $srcLoc = new SourceCodeLocation(__FILE__, __LINE__ + 2);
        $firstCallArgs = array_merge($firstCall->argsPrefix, [new StackTraceUtilTestDummyCodeArgs($stackTraceUtil, $callKindsSeq, /* currentCallIndex */ 0, $srcLoc, /* expectations */ [])]);
        $retVal = ($firstCall->callable)(...$firstCallArgs);
        $dbgCtx->add(['retVal' => $retVal]);
        StackTraceExpectations::fromFramesExpectations($retVal->expectations, /* allowToBePrefixOfActual */ true)->assertMatches($retVal->actual);
    }

    private const DEPTH_BEFORE_CALL_USER_FUNC_KEY = 'depth_before_call_user_func';
    private const DEPTH_AFTER_CALL_USER_FUNC_KEY = 'depth_after_call_user_func';
    private const IS_CALL_USER_FUNC_ARRAY_VARIANT_KEY = 'is_call_user_func_array_variant';

    /**
     * @return iterable<string, array{MixedMap}>
     */
    public function dataProviderForTestCaptureInApmFormatWithCallUserFunc(): iterable
    {
        $depthVariants = [1, 2];

        $result = (new DataProviderForTestBuilder())
            ->addBoolKeyedDimensionAllValuesCombinable(self::IS_CALL_USER_FUNC_ARRAY_VARIANT_KEY)
            ->addKeyedDimensionAllValuesCombinable(self::DEPTH_BEFORE_CALL_USER_FUNC_KEY, $depthVariants)
            ->addKeyedDimensionAllValuesCombinable(self::DEPTH_AFTER_CALL_USER_FUNC_KEY, $depthVariants)
            ->addKeyedDimensionAllValuesCombinable(self::NUMBER_OF_STACK_FRAMES_TO_SKIP_KEY, DataProviderForTestBuilder::rangeUpTo(5))
            ->build();

        return DataProviderForTestBuilder::convertEachDataSetToMixedMap($result);
    }

    /**
     * @param MixedMap                      $testArgs
     * @param int                           $depth
     * @param StackTraceFrameExpectations[] $framesExpectations
     *
     * @return array{StackTraceFrameExpectations[], StackTraceFrame[]}
     */
    private function helperForTestCaptureInApmFormatWithCallUserFunc(MixedMap $testArgs, int $depth, array $framesExpectations): array
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, AssertMessageStack::funcArgs());
        $thisFuncAsCallable = [__CLASS__, __FUNCTION__];
        $depthBeforeCallUserFunc = $testArgs->getInt(self::DEPTH_BEFORE_CALL_USER_FUNC_KEY);
        $depthAfterCallUserFunc = $testArgs->getInt(self::DEPTH_AFTER_CALL_USER_FUNC_KEY);
        self::assertLessThanOrEqual($depthBeforeCallUserFunc + $depthAfterCallUserFunc, $depth);
        if ($depth === $depthBeforeCallUserFunc + $depthAfterCallUserFunc) {
            $numberOfStackFramesToSkip = $testArgs->getPositiveOrZeroInt(self::NUMBER_OF_STACK_FRAMES_TO_SKIP_KEY);
            array_unshift($framesExpectations, StackTraceFrameExpectations::fromLocationOnly(__FILE__, __LINE__ + 1));
            $actuallyCatpuredStackTrace = self::stackTraceUtil()->captureInApmFormat($numberOfStackFramesToSkip, /* maxNumberOfFrames */ null);
            return [array_slice($framesExpectations, $numberOfStackFramesToSkip), $actuallyCatpuredStackTrace];
        }
        if ($depth < $depthBeforeCallUserFunc || $depth > $depthBeforeCallUserFunc) {
            array_unshift($framesExpectations, StackTraceFrameExpectations::fromClassMethod(__FILE__, __LINE__ + 1, __CLASS__, /* isStatic */ false, __FUNCTION__));
            return $this->helperForTestCaptureInApmFormatWithCallUserFunc($testArgs, $depth + 1, $framesExpectations);
        }

        self::assertSame($depthBeforeCallUserFunc, $depth);
        $isCallUserFuncArrayVariant = $testArgs->getBool(self::IS_CALL_USER_FUNC_ARRAY_VARIANT_KEY);
        $framesExpectationForCallToThisFuncByCallUserFunc = StackTraceFrameExpectations::fromClassMethodNoLocation(__CLASS__, /* isStatic */ false, __FUNCTION__);
        $callUserFuncLine = __LINE__ + 6;
        array_unshift($framesExpectations, StackTraceFrameExpectations::fromStandaloneFunction(__FILE__, $callUserFuncLine, $isCallUserFuncArrayVariant ? 'call_user_func_array' : 'call_user_func'));
        array_unshift($framesExpectations, $framesExpectationForCallToThisFuncByCallUserFunc);
        $callArgs = [$testArgs, $depth + 1, $framesExpectations];
        self::assertSame(__LINE__ + 2, $callUserFuncLine);
        /** @var array{StackTraceFrameExpectations[], StackTraceFrame[]} $retVal */
        $retVal = $isCallUserFuncArrayVariant ? call_user_func_array($thisFuncAsCallable, $callArgs) : call_user_func($thisFuncAsCallable, ...$callArgs);
        return $retVal;
    }

    /**
     * @dataProvider dataProviderForTestCaptureInApmFormatWithCallUserFunc
     */
    public function testCaptureInApmFormatWithCallUserFunc(MixedMap $testArgs): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, AssertMessageStack::funcArgs());
        $framesExpectations = [StackTraceFrameExpectations::fromClassMethodUnknownLocation(__CLASS__, /* isStatic */ false, 'testCaptureInApmFormatWithCallUserFunc')];
        array_unshift($framesExpectations, StackTraceFrameExpectations::fromClassMethod(__FILE__, __LINE__ + 1, __CLASS__, /* isStatic */ false, 'helperForTestCaptureInApmFormatWithCallUserFunc'));
        $expectedActual = $this->helperForTestCaptureInApmFormatWithCallUserFunc($testArgs, /* depth */ 1, $framesExpectations);
        $dbgCtx->add(['expectedActual' => $expectedActual]);
        self::assertCount(2, $expectedActual);
        StackTraceExpectations::fromFramesExpectations($expectedActual[0], /* allowExpectedStackTraceToBePrefix */ true)->assertMatches($expectedActual[1]);
    }

    /**
     * @param bool         $includeArgs
     * @param int          $expectedArgsCount
     * @param null|mixed[] $actualFrameArgs
     *
     * @return void
     */
    private static function assertFrameArgsCount(bool $includeArgs, int $expectedArgsCount, ?array $actualFrameArgs): void
    {
        if ($includeArgs) {
            self::assertNotNull($actualFrameArgs);
            self::assertCount($expectedArgsCount, $actualFrameArgs);
        } else {
            self::assertNull($actualFrameArgs);
        }
    }

    /**
     * @param bool         $includeArgs
     * @param int          $argIndex
     * @param mixed        $expectedValue
     * @param null|mixed[] $actualFrameArgs
     *
     * @return void
     *
     * @noinspection PhpSameParameterValueInspection
     */
    private static function assertFrameArgSame(bool $includeArgs, int $argIndex, $expectedValue, ?array $actualFrameArgs): void
    {
        if ($includeArgs) {
            self::assertNotNull($actualFrameArgs);
            self::assertCountAtLeast($argIndex, $actualFrameArgs);
            self::assertArrayHasKeyWithValue($argIndex, $expectedValue, $actualFrameArgs);
        }
    }

    /**
     * @param bool    $includeThisObj
     * @param object  $expectedThisObj
     * @param ?object $actualFrameThisObj
     *
     * @return void
     */
    private static function assertFrameThisObjSame(bool $includeThisObj, object $expectedThisObj, ?object $actualFrameThisObj): void
    {
        if ($includeThisObj) {
            self::assertSame($expectedThisObj, $actualFrameThisObj);
        } else {
            self::assertNull($actualFrameThisObj);
        }
    }

    /**
     * @return iterable<string, array{MixedMap}>
     */
    public function dataProviderForTestCaptureInClassicFormat(): iterable
    {
        $result = (new DataProviderForTestBuilder())
            ->addKeyedDimensionAllValuesCombinable(self::NUMBER_OF_STACK_FRAMES_TO_SKIP_KEY, [0, 1, 2, 3, 4, 5, 10, self::VERY_LARGE_STACK_TRACE_SIZE_LIMIT])
            ->addKeyedDimensionAllValuesCombinable(self::MAX_NUMBER_OF_FRAMES_KEY, [null, 0, 1, 2, 3, 4, 5, 10, self::VERY_LARGE_STACK_TRACE_SIZE_LIMIT])
            ->addBoolKeyedDimensionAllValuesCombinable(self::KEEP_ELASTIC_APM_FRAMES_KEY)
            ->addBoolKeyedDimensionAllValuesCombinable(self::INCLUDE_ARGS_KEY)
            ->addBoolKeyedDimensionAllValuesCombinable(self::INCLUDE_THIS_OBJ_KEY)
            ->build();

        return DataProviderForTestBuilder::convertEachDataSetToMixedMap($result);
    }

    /**
     * @dataProvider dataProviderForTestCaptureInClassicFormat
     */
    public function testCaptureInClassicFormatOneTestFrame(MixedMap $testArgs): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, AssertMessageStack::funcArgs());

        $numberOfFramesToSkip = $testArgs->getPositiveOrZeroInt(self::NUMBER_OF_STACK_FRAMES_TO_SKIP_KEY);
        $maxNumberOfFrames = $testArgs->getNullablePositiveOrZeroInt(self::MAX_NUMBER_OF_FRAMES_KEY);
        $keepElasticApmFrames = $testArgs->getBool(self::KEEP_ELASTIC_APM_FRAMES_KEY);
        $includeArgs = $testArgs->getBool(self::INCLUDE_ARGS_KEY);
        $includeThisObj = $testArgs->getBool(self::INCLUDE_THIS_OBJ_KEY);

        $phpFormatStackTrace = debug_backtrace();
        $dbgCtx->add(['phpFormatStackTrace' => $phpFormatStackTrace]);
        if ($maxNumberOfFrames === 0) {
            $lineCaptureCall = -1;
            $actualCapturedStackTrace = [];
        } else {
            $lineCaptureCall = __LINE__ + 1;
            $actualCapturedStackTrace = self::stackTraceUtil()->captureInClassicFormat($numberOfFramesToSkip, $maxNumberOfFrames, $keepElasticApmFrames, $includeArgs, $includeThisObj);
        }
        $dbgCtx->add(['actualCapturedStackTrace' => $actualCapturedStackTrace]);

        if ($maxNumberOfFrames === 0 || $numberOfFramesToSkip >= count($phpFormatStackTrace)) {
            self::assertEmpty($actualCapturedStackTrace);
        } else {
            self::assertNotEmpty($actualCapturedStackTrace);
        }

        if ($numberOfFramesToSkip === 0 && $maxNumberOfFrames !== 0) {
            $frame = $actualCapturedStackTrace[0];
            self::assertSame(__FUNCTION__, $frame->function);
            self::assertSame(__FILE__, $frame->file);
            self::assertSame($lineCaptureCall, $frame->line);
            self::assertSame(__CLASS__, $frame->class);
            self::assertFalse($frame->isStaticMethod);
            self::assertFrameThisObjSame($includeThisObj, $this, $frame->thisObj);
            self::assertFrameArgsCount($includeArgs, 1, $frame->args);
            self::assertFrameArgSame($includeArgs, /* testArgs param index */ 0, $testArgs, $frame->args);
        } elseif (!ArrayUtil::isEmpty($actualCapturedStackTrace)) {
            $frame = $actualCapturedStackTrace[0];
            self::assertNotEquals(__FUNCTION__, $frame->function);
        }
    }

    /**
     * @param MixedMap                $testArgs
     * @param array<string, mixed>[] &$expectedFramesProps
     * @param array<string, mixed>   &$dbgStackTrace
     *
     * @return ClassicFormatStackTraceFrame[]
     *
     * @param-out array<string, mixed> $dbgStackTrace
     */
    private function helper1ForTestCaptureInClassicFormatMultipleTestFrames(MixedMap $testArgs, array &$expectedFramesProps, ?array &$dbgStackTrace): array
    {
        array_unshift(/* ref */ $expectedFramesProps, [StackTraceUtil::LINE_KEY => __LINE__ + 1]);
        return self::helper2StaticForTestCaptureInClassicFormatMultipleTestFrames($testArgs, $expectedFramesProps, $dbgStackTrace);
    }

    /**
     * @param MixedMap                $testArgs
     * @param array<string, mixed>[] &$expectedFramesProps
     * @param array<string, mixed>   &$dbgStackTrace
     *
     * @return ClassicFormatStackTraceFrame[]
     *
     * @param-out array<string, mixed> $dbgStackTrace
     */
    private static function helper2StaticForTestCaptureInClassicFormatMultipleTestFrames(MixedMap $testArgs, array &$expectedFramesProps, ?array &$dbgStackTrace): array
    {
        $func = function () use ($testArgs, &$expectedFramesProps, &$dbgStackTrace): array {
            array_unshift(/* ref */ $expectedFramesProps, [StackTraceUtil::LINE_KEY => DUMMY_FUNC_FOR_TESTS_WITHOUT_NAMESPACE_CALLABLE_LINE_NUMBER]);
            $numberOfFramesToSkip = $testArgs->getPositiveOrZeroInt(self::NUMBER_OF_STACK_FRAMES_TO_SKIP_KEY);
            $maxNumberOfFrames = $testArgs->getNullablePositiveOrZeroInt(self::MAX_NUMBER_OF_FRAMES_KEY);
            $keepElasticApmFrames = $testArgs->getBool(self::KEEP_ELASTIC_APM_FRAMES_KEY);
            $includeArgs = $testArgs->getBool(self::INCLUDE_ARGS_KEY);
            $includeThisObj = $testArgs->getBool(self::INCLUDE_THIS_OBJ_KEY);
            $dbgStackTrace = debug_backtrace();
            array_unshift(/* ref */ $expectedFramesProps, [StackTraceUtil::LINE_KEY => __LINE__ + 1, StackTraceUtil::FUNCTION_KEY => __FUNCTION__]);
            return $maxNumberOfFrames === 0 ? [] : self::stackTraceUtil()->captureInClassicFormat($numberOfFramesToSkip, $maxNumberOfFrames, $keepElasticApmFrames, $includeArgs, $includeThisObj);
        };

        array_unshift(/* ref */ $expectedFramesProps, [StackTraceUtil::LINE_KEY => __LINE__ + 1]);
        return dummyFuncForTestsWithoutNamespace($func);
    }

    /**
     * @dataProvider dataProviderForTestCaptureInClassicFormat
     */
    public function testCaptureInClassicFormatMultipleTestFrames(MixedMap $testArgs): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, AssertMessageStack::funcArgs());

        $numberOfFramesToSkip = $testArgs->getPositiveOrZeroInt(self::NUMBER_OF_STACK_FRAMES_TO_SKIP_KEY);
        $maxNumberOfFrames = $testArgs->getNullablePositiveOrZeroInt(self::MAX_NUMBER_OF_FRAMES_KEY);
        $includeArgs = $testArgs->getBool(self::INCLUDE_ARGS_KEY);
        $includeThisObj = $testArgs->getBool(self::INCLUDE_THIS_OBJ_KEY);

        /** @var array<string, mixed>[] $expectedFramesProps */
        $expectedFramesProps = [];
        array_unshift(/* ref */ $expectedFramesProps, [StackTraceUtil::LINE_KEY => __LINE__ + 1]);
        $actualCapturedStackTrace = self::helper1ForTestCaptureInClassicFormatMultipleTestFrames($testArgs, /* out */ $expectedFramesProps, /* out */ $dbgStackTrace);
        $dbgCtx->add(['actualCapturedStackTrace' => $actualCapturedStackTrace, 'expectedFramesProps' => $expectedFramesProps, 'dbgStackTrace' => $dbgStackTrace]);

        if ($maxNumberOfFrames === 0 || $numberOfFramesToSkip >= count($dbgStackTrace)) {
            self::assertEmpty($actualCapturedStackTrace);
        } else {
            self::assertNotEmpty($actualCapturedStackTrace);
        }

        $shouldHaveFrame = function (int $fullStackFrameIndex) use ($numberOfFramesToSkip, $maxNumberOfFrames): bool {
            return $numberOfFramesToSkip <= $fullStackFrameIndex && ($maxNumberOfFrames === null || $maxNumberOfFrames > ($fullStackFrameIndex - $numberOfFramesToSkip));
        };

        $actualCapturedStackTraceFrameIndex = 0;
        $dbgCtx->add(['actualCapturedStackTraceFrameIndex' => &$actualCapturedStackTraceFrameIndex]);
        $fullStackFrameIndex = 0;
        $dbgCtx->add(['fullStackFrameIndex' => &$fullStackFrameIndex]);

        if ($shouldHaveFrame($fullStackFrameIndex)) {
            $frame = $actualCapturedStackTrace[$actualCapturedStackTraceFrameIndex];
            self::assertSame($expectedFramesProps[$fullStackFrameIndex][StackTraceUtil::FUNCTION_KEY], $frame->function);
            self::assertSame(__FILE__, $frame->file);
            self::assertSame($expectedFramesProps[$fullStackFrameIndex][StackTraceUtil::LINE_KEY], $frame->line);
            self::assertSame(__CLASS__, $frame->class);
            self::assertTrue($frame->isStaticMethod);
            self::assertNull($frame->thisObj);
            self::assertFrameArgsCount($includeArgs, 0, $frame->args);
            ++$actualCapturedStackTraceFrameIndex;
        }

        ++$fullStackFrameIndex;
        if ($shouldHaveFrame($fullStackFrameIndex)) {
            $frame = $actualCapturedStackTrace[$actualCapturedStackTraceFrameIndex];
            self::assertSame('dummyFuncForTestsWithoutNamespace', $frame->function);
            self::assertSame(DUMMY_FUNC_FOR_TESTS_WITHOUT_NAMESPACE_CALLABLE_FILE_NAME, $frame->file);
            self::assertSame($expectedFramesProps[$fullStackFrameIndex][StackTraceUtil::LINE_KEY], $frame->line);
            self::assertNull($frame->class);
            self::assertNull($frame->isStaticMethod);
            self::assertNull($frame->thisObj);
            self::assertFrameArgsCount($includeArgs, 1, $frame->args);
            ++$actualCapturedStackTraceFrameIndex;
        }

        ++$fullStackFrameIndex;
        if ($shouldHaveFrame($fullStackFrameIndex)) {
            $frame = $actualCapturedStackTrace[$actualCapturedStackTraceFrameIndex];
            self::assertSame('helper2StaticForTestCaptureInClassicFormatMultipleTestFrames', $frame->function);
            self::assertSame(__FILE__, $frame->file);
            self::assertSame($expectedFramesProps[$fullStackFrameIndex][StackTraceUtil::LINE_KEY], $frame->line);
            self::assertSame(__CLASS__, $frame->class);
            self::assertTrue($frame->isStaticMethod);
            self::assertNull($frame->thisObj);
            self::assertFrameArgsCount($includeArgs, 3, $frame->args);
            self::assertFrameArgSame($includeArgs, /* testArgs param index */ 0, $testArgs, $frame->args);
            ++$actualCapturedStackTraceFrameIndex;
        }

        ++$fullStackFrameIndex;
        if ($shouldHaveFrame($fullStackFrameIndex)) {
            $frame = $actualCapturedStackTrace[$actualCapturedStackTraceFrameIndex];
            self::assertSame('helper1ForTestCaptureInClassicFormatMultipleTestFrames', $frame->function);
            self::assertSame(__FILE__, $frame->file);
            self::assertSame($expectedFramesProps[$fullStackFrameIndex][StackTraceUtil::LINE_KEY], $frame->line);
            self::assertSame(__CLASS__, $frame->class);
            self::assertFalse($frame->isStaticMethod);
            self::assertFrameThisObjSame($includeThisObj, $this, $frame->thisObj);
            self::assertFrameArgsCount($includeArgs, 3, $frame->args);
            self::assertFrameArgSame($includeArgs, /* testArgs param index */ 0, $testArgs, $frame->args);
            ++$actualCapturedStackTraceFrameIndex;
        }

        ++$fullStackFrameIndex;
        if ($shouldHaveFrame($fullStackFrameIndex)) {
            $frame = $actualCapturedStackTrace[$actualCapturedStackTraceFrameIndex];
            self::assertSame(__FUNCTION__, $frame->function);
            self::assertSame(__FILE__, $frame->file);
            self::assertSame($expectedFramesProps[$fullStackFrameIndex][StackTraceUtil::LINE_KEY], $frame->line);
            self::assertSame(__CLASS__, $frame->class);
            self::assertFalse($frame->isStaticMethod);
            self::assertFrameThisObjSame($includeThisObj, $this, $frame->thisObj);
            self::assertFrameArgsCount($includeArgs, 1, $frame->args);
            self::assertFrameArgSame($includeArgs, /* testArgs param index */ 0, $testArgs, $frame->args);
        } elseif ($actualCapturedStackTraceFrameIndex <= count($actualCapturedStackTrace) - 1) {
            $frame = $actualCapturedStackTrace[$actualCapturedStackTraceFrameIndex];
            self::assertNotEquals(__FUNCTION__, $frame->function);
        }
    }

    /**
     * @return iterable<array{iterable<ClassicFormatStackTraceFrame>, StackTraceFrame[]}>
     */
    private static function dataProviderForTestConvertClassicToApmFormatDataSets(): iterable
    {
        $buildApmFromNonLocationProperties = function (?string $classicName, ?bool $isStaticMethod, ?string $methodName): StackTraceFrame {
            return new StackTraceFrame(
                StackTraceUtil::FILE_NAME_NOT_AVAILABLE_SUBSTITUTE,
                StackTraceUtil::LINE_NUMBER_NOT_AVAILABLE_SUBSTITUTE,
                StackTraceUtil::buildApmFormatFunctionForClassMethod($classicName, $isStaticMethod, $methodName)
            );
        };

        yield [
            [
                new ClassicFormatStackTraceFrame('app_bootstrap.php', 321),
            ],
            [
                new StackTraceFrame('app_bootstrap.php', 321),
            ]
        ];

        yield [
            [
                new ClassicFormatStackTraceFrame('app_bootstrap.php', 456, 'MyClass', /* isStaticMethod */ true, 'myMethod'),
            ],
            [
                new StackTraceFrame('app_bootstrap.php', 456),
                $buildApmFromNonLocationProperties('MyClass', /* isStaticMethod */ true, 'myMethod'),
            ]
        ];

        yield [
            [
                new ClassicFormatStackTraceFrame('AppClass.php', 20, 'AppClass', /* isStaticMethod */ false, 'myMethod'),
                new ClassicFormatStackTraceFrame('app_bootstrap.php', 10),
            ],
            [
                new StackTraceFrame('AppClass.php', 20),
                new StackTraceFrame('app_bootstrap.php', 10, StackTraceUtil::buildApmFormatFunctionForClassMethod('AppClass', /* isStaticMethod */ false, 'myMethod')),
            ]
        ];

        yield [
            [
                new ClassicFormatStackTraceFrame('AppClass3.php', 333, 'AppClass3', /* isStaticMethod */ false, 'myMethod3'),
                new ClassicFormatStackTraceFrame('AppClass2.php', 22, 'AppClass2', /* isStaticMethod */ true, 'myMethod2'),
                new ClassicFormatStackTraceFrame('app_bootstrap.php', 1234),
            ],
            [
                new StackTraceFrame('AppClass3.php', 333),
                new StackTraceFrame('AppClass2.php', 22, StackTraceUtil::buildApmFormatFunctionForClassMethod('AppClass3', /* isStaticMethod */ false, 'myMethod3')),
                new StackTraceFrame('app_bootstrap.php', 1234, StackTraceUtil::buildApmFormatFunctionForClassMethod('AppClass2', /* isStaticMethod */ true, 'myMethod2')),
            ]
        ];

        yield [
            [
                new ClassicFormatStackTraceFrame(/* file */ null, /* line */ null, /* class */ null, /* isStaticMethod */ null, 'call_user_func_array'),
                new ClassicFormatStackTraceFrame('class-wp-hook.php', 11, 'WP_Hook', /* isStaticMethod */ false, 'apply_filters'),
                new ClassicFormatStackTraceFrame('plugin.php', 10, /* class */ null, /* isStaticMethod */ null, 'apply_filters'),
                new ClassicFormatStackTraceFrame('my_pugin.php', 9, /* class */ null, /* isStaticMethod */ null, 'MyPluginNamespace\\myPluginFilterCallback'),
                new ClassicFormatStackTraceFrame('class-wp-hook.php', 8, 'WP_Hook', /* isStaticMethod */ false, 'do_action'),
                new ClassicFormatStackTraceFrame('plugin.php', 7, /* class */ null, /* isStaticMethod */ null, 'do_action'),
                new ClassicFormatStackTraceFrame('my_theme.php', 6, 'MyTheme', /* isStaticMethod */ false, 'filterCallback'),
                new ClassicFormatStackTraceFrame(/* file */ null, /* line */ null, /* class */ null, /* isStaticMethod */ null, 'call_user_func'),
                new ClassicFormatStackTraceFrame('class-wp-hook.php', 4, 'WP_Hook', /* isStaticMethod */ false, 'apply_filters'),
                new ClassicFormatStackTraceFrame('plugin.php', 3, /* class */ null, /* isStaticMethod */ null, 'apply_filters'),
                new ClassicFormatStackTraceFrame('option.php', 2, /* class */ null, /* isStaticMethod */ null, 'require'),
                new ClassicFormatStackTraceFrame('index.php', 1),
            ],
            [
                new StackTraceFrame('class-wp-hook.php', 11, StackTraceUtil::buildApmFormatFunctionForClassMethod(/* class */ null, /* isStaticMethod */ null, 'call_user_func_array')),
                new StackTraceFrame('plugin.php', 10, StackTraceUtil::buildApmFormatFunctionForClassMethod('WP_Hook', /* isStaticMethod */ false, 'apply_filters')),
                new StackTraceFrame('my_pugin.php', 9, StackTraceUtil::buildApmFormatFunctionForClassMethod(/* class */ null, /* isStaticMethod */ null, 'apply_filters')),
                new StackTraceFrame('class-wp-hook.php', 8, StackTraceUtil::buildApmFormatFunctionForClassMethod(null, null, 'MyPluginNamespace\\myPluginFilterCallback')),
                new StackTraceFrame('plugin.php', 7, StackTraceUtil::buildApmFormatFunctionForClassMethod('WP_Hook', /* isStaticMethod */ false, 'do_action')),
                new StackTraceFrame('my_theme.php', 6, StackTraceUtil::buildApmFormatFunctionForClassMethod(/* class */ null, /* isStaticMethod */ null, 'do_action')),
                $buildApmFromNonLocationProperties('MyTheme', /* isStaticMethod */ false, 'filterCallback'),
                new StackTraceFrame('class-wp-hook.php', 4, StackTraceUtil::buildApmFormatFunctionForClassMethod(/* class */ null, /* isStaticMethod */ null, 'call_user_func')),
                new StackTraceFrame('plugin.php', 3, StackTraceUtil::buildApmFormatFunctionForClassMethod('WP_Hook', /* isStaticMethod */ false, 'apply_filters')),
                new StackTraceFrame('option.php', 2, StackTraceUtil::buildApmFormatFunctionForClassMethod(/* class */ null, /* isStaticMethod */ null, 'apply_filters')),
                new StackTraceFrame('index.php', 1, 'require'),
            ]
        ];

        yield [[], []];
    }

    /**
     * @return iterable<string, array{MixedMap}>
     */
    public function dataProviderForTestConvertClassicToApmFormat(): iterable
    {
        /**
         * @return iterable<array<string, mixed>>
         */
        $genDataSets = function (): iterable {
            foreach (self::dataProviderForTestConvertClassicToApmFormatDataSets() as [$input, $fullExpectedOutput]) {
                $result = [self::INPUT_KEY => $input, self::FULL_EXPECTED_OUTPUT_KEY => $fullExpectedOutput];
                $maxNumberOfFramesVariants = [null, 1, 2, 3, 4, 5, 10, self::VERY_LARGE_STACK_TRACE_SIZE_LIMIT];
                $fullExpectedOutputCount = count($fullExpectedOutput);
                if ($fullExpectedOutputCount !== 0) {
                    ArrayUtilForTests::addToListIfNotAlreadyPresent($fullExpectedOutputCount - 1, /* ref */ $maxNumberOfFramesVariants);
                }
                ArrayUtilForTests::addToListIfNotAlreadyPresent($fullExpectedOutputCount, /* ref */ $maxNumberOfFramesVariants);
                ArrayUtilForTests::addToListIfNotAlreadyPresent($fullExpectedOutputCount + 1, /* ref */ $maxNumberOfFramesVariants);
                foreach ($maxNumberOfFramesVariants as $maxNumberOfFrames) {
                    $result[self::MAX_NUMBER_OF_FRAMES_KEY] = $maxNumberOfFrames;
                    yield $result;
                }
            }
        };

        return DataProviderForTestBuilder::convertEachDataSetToMixedMapAndAddDesc($genDataSets);
    }

    /**
     * @dataProvider dataProviderForTestConvertClassicToApmFormat
     */
    public function testConvertClassicToApmFormat(MixedMap $testArgs): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, AssertMessageStack::funcArgs());
        $maxNumberOfFrames = $testArgs->getNullablePositiveOrZeroInt(self::MAX_NUMBER_OF_FRAMES_KEY);
        $input = $testArgs->getArray(self::INPUT_KEY);
        /** @var ClassicFormatStackTraceFrame[] $input */
        $fullExpectedOutput = $testArgs->getArray(self::FULL_EXPECTED_OUTPUT_KEY);
        /** @var StackTraceFrame[] $fullExpectedOutput */

        $actualOutputFrames = $maxNumberOfFrames === 0 ? [] : self::stackTraceUtil()->convertClassicToApmFormat($input, $maxNumberOfFrames);
        $dbgCtx->add(['actualOutputFrames' => $actualOutputFrames]);
        $expectedOutput = $maxNumberOfFrames === null ? $fullExpectedOutput : array_slice($fullExpectedOutput, /* offset */ 0, /* length */ $maxNumberOfFrames);
        StackTraceExpectations::fromFrames($expectedOutput)->assertMatches($actualOutputFrames);
    }

    /**
     * @return iterable<string, array{MixedMap}>
     */
    public function dataProviderForTestConvertThrowableTraceToApmFormat(): iterable
    {
        $result = (new DataProviderForTestBuilder())
            ->addKeyedDimensionAllValuesCombinable(self::MAX_NUMBER_OF_FRAMES_KEY, [null, 0, 1, 2, 3, 4, 5, 10, self::VERY_LARGE_STACK_TRACE_SIZE_LIMIT])
            ->build();

        return DataProviderForTestBuilder::convertEachDataSetToMixedMap($result);
    }

    /**
     * @param MixedMap                $testArgs
     * @param ?int                   &$lineWithThrow
     * @param ?int                   &$lineWithCallToDummyFunc
     * @param null|StackTraceFrame[] &$directlyCapturedStackTrace
     *
     * @throws DummyExceptionForTests
     */
    private static function helperForTestConvertThrowableTraceToApmFormat(MixedMap $testArgs, ?int &$lineWithThrow, ?int &$lineWithCallToDummyFunc, ?array &$directlyCapturedStackTrace): void
    {
        $func = function () use ($testArgs, &$lineWithThrow, &$directlyCapturedStackTrace): void {
            $maxNumberOfFrames = $testArgs->getNullablePositiveOrZeroInt(self::MAX_NUMBER_OF_FRAMES_KEY);
            $lineWithThrow = __LINE__ + 2;
            $directlyCapturedStackTrace = $maxNumberOfFrames === 0 ? [] : self::stackTraceUtil()->captureInApmFormat(/* numberOfFramesToSkip */ 0, $maxNumberOfFrames);
            throw new DummyExceptionForTests('Dummy message');
        };

        $lineWithCallToDummyFunc = __LINE__ + 1;
        dummyFuncForTestsWithoutNamespace($func);
    }

    /**
     * @dataProvider dataProviderForTestConvertThrowableTraceToApmFormat
     */
    public function testConvertThrowableTraceToApmFormat(MixedMap $testArgs): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, AssertMessageStack::funcArgs());
        $maxNumberOfFrames = $testArgs->getNullablePositiveOrZeroInt(self::MAX_NUMBER_OF_FRAMES_KEY);

        $lineWithThrow = 0;
        $lineWithCallToDummyFunc = 0;
        $directlyCapturedStackTrace = [];
        $thrownStackTrace = null;
        try {
            $lineWithCallToHelperFunc = __LINE__ + 1;
            self::helperForTestConvertThrowableTraceToApmFormat($testArgs, /* out */ $lineWithThrow, /* out */ $lineWithCallToDummyFunc, /* out */ $directlyCapturedStackTrace);
        } catch (DummyExceptionForTests $e) {
            self::assertSame('Dummy message', $e->getMessage());
            $thrownStackTrace = $maxNumberOfFrames === 0 ? [] : self::stackTraceUtil()->convertThrowableTraceToApmFormat($e, $maxNumberOfFrames);
        }
        $dbgCtx->add(['lineWithThrow' => $lineWithThrow, 'directlyCapturedStackTrace' => $directlyCapturedStackTrace, 'thrownExStackTrace' => $thrownStackTrace]);
        self::assertNotNull($thrownStackTrace);

        if ($maxNumberOfFrames === 0) {
            self::assertEmpty($directlyCapturedStackTrace);
        } else {
            self::assertNotEmpty($directlyCapturedStackTrace);
            $directlyCapturedStackTrace[0]->lineno = $lineWithThrow;
        }

        StackTraceExpectations::fromFrames($directlyCapturedStackTrace)->assertMatches($thrownStackTrace);

        $shouldHaveFrame = function (int $fullStackFrameIndex) use ($maxNumberOfFrames): bool {
            return $maxNumberOfFrames === null || $maxNumberOfFrames > $fullStackFrameIndex;
        };

        $thrownStackTraceFrameIndex = 0;
        $dbgCtx->add(['thrownStackTraceFrameIndex' => &$thrownStackTraceFrameIndex]);
        $fullStackFrameIndex = 0;
        $dbgCtx->add(['fullStackFrameIndex' => &$fullStackFrameIndex]);

        if ($shouldHaveFrame($fullStackFrameIndex)) {
            $frame = $thrownStackTrace[$thrownStackTraceFrameIndex];
            self::assertNull($frame->function);
            self::assertSame(__FILE__, $frame->filename);
            self::assertSame($lineWithThrow, $frame->lineno);
            ++$thrownStackTraceFrameIndex;
        }

        ++$fullStackFrameIndex;
        if ($shouldHaveFrame($fullStackFrameIndex)) {
            StackTraceFrameExpectations::fromClosure(
                DUMMY_FUNC_FOR_TESTS_WITHOUT_NAMESPACE_CALLABLE_FILE_NAME,
                DUMMY_FUNC_FOR_TESTS_WITHOUT_NAMESPACE_CALLABLE_LINE_NUMBER,
                __NAMESPACE__,
                __CLASS__,
                /* isStatic */ true
            )->assertMatches($thrownStackTrace[$thrownStackTraceFrameIndex]);
            ++$thrownStackTraceFrameIndex;
        }

        ++$fullStackFrameIndex;
        if ($shouldHaveFrame($fullStackFrameIndex)) {
            $frame = $thrownStackTrace[$thrownStackTraceFrameIndex];
            self::assertSame('dummyFuncForTestsWithoutNamespace', $frame->function);
            self::assertSame(__FILE__, $frame->filename);
            self::assertSame($lineWithCallToDummyFunc, $frame->lineno);
            ++$thrownStackTraceFrameIndex;
        }

        ++$fullStackFrameIndex;
        if ($shouldHaveFrame($fullStackFrameIndex)) {
            StackTraceFrameExpectations::fromClassMethod(__FILE__, $lineWithCallToHelperFunc, __CLASS__, /* isStatic */ true, 'helperForTestConvertThrowableTraceToApmFormat')
                                       ->assertMatches($thrownStackTrace[$thrownStackTraceFrameIndex]);
            ++$thrownStackTraceFrameIndex;
        }

        ++$fullStackFrameIndex;
        if ($shouldHaveFrame($fullStackFrameIndex)) {
            $frame = $thrownStackTrace[$thrownStackTraceFrameIndex];
            StackTraceFrameExpectations::fromClassMethodUnknownLocation(__CLASS__, /* isStatic */ false, __FUNCTION__)->assertMatches($frame);
            self::assertNotEquals(StackTraceUtil::FILE_NAME_NOT_AVAILABLE_SUBSTITUTE, $frame->filename);
            self::assertNotEquals(StackTraceUtil::LINE_NUMBER_NOT_AVAILABLE_SUBSTITUTE, $frame->lineno);
        } elseif ($thrownStackTraceFrameIndex <= count($thrownStackTrace) - 1) {
            $frame = $thrownStackTrace[$thrownStackTraceFrameIndex];
            self::assertNotNull($frame->function);
            self::assertStringNotContainsString(__FUNCTION__, $frame->function);
        }
    }

    public function testLimitConfigToMaxNumberOfFrames(): void
    {
        self::assertSame(null, StackTraceUtil::convertLimitConfigToMaxNumberOfFrames(-1));
        self::assertSame(null, StackTraceUtil::convertLimitConfigToMaxNumberOfFrames(-2));
        self::assertSame(null, StackTraceUtil::convertLimitConfigToMaxNumberOfFrames(-5));

        self::assertSame(0, StackTraceUtil::convertLimitConfigToMaxNumberOfFrames(0));
        self::assertSame(1, StackTraceUtil::convertLimitConfigToMaxNumberOfFrames(1));
        self::assertSame(2, StackTraceUtil::convertLimitConfigToMaxNumberOfFrames(2));
        self::assertSame(5, StackTraceUtil::convertLimitConfigToMaxNumberOfFrames(5));
    }

    private static function assertSameClassicFormatStackTraceFrames(ClassicFormatStackTraceFrame $expected, ClassicFormatStackTraceFrame $actual): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, AssertMessageStack::funcArgs());

        self::assertCount(count(get_object_vars($expected)), get_object_vars($actual));

        $dbgCtx->pushSubScope();
        foreach (get_object_vars($expected) as $propName => $expectedPropVal) {
            $dbgCtx->clearCurrentSubScope(compact('propName', 'expectedPropVal'));
            self::assertSame($expectedPropVal, $actual->{$propName});
        }
        $dbgCtx->popSubScope();
    }

    /**
     * @param ClassicFormatStackTraceFrame[] $expected
     * @param ClassicFormatStackTraceFrame[] $actual
     *
     * @return void
     */
    private static function assertSameClassicFormatStackTraces(array $expected, array $actual): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, AssertMessageStack::funcArgs());

        $dbgCtx->add(compact('expected', 'actual'));
        self::assertCount(count($expected), $actual);

        $dbgCtx->pushSubScope();
        foreach (RangeUtil::generateUpTo(count($expected)) as $frameIndex) {
            $dbgCtx->clearCurrentSubScope(compact('frameIndex'));
            self::assertSameClassicFormatStackTraceFrames($expected[$frameIndex], $actual[$frameIndex]);
        }
        $dbgCtx->popSubScope();
    }

    /**
     * @dataProvider boolDataProvider
     */
    public function testConvertCaptureToClassicFormatSleepExample(bool $addDummyLocationToSleepFunc): void
    {
        $input = [
            [
                'file'     => '/my_work/apm-agent-php/agent/php/ElasticApm/Impl/Util/StackTraceUtil.php',
                'line'     => 131,
                'function' => 'captureInClassicFormat',
                'class'    => 'Elastic\Apm\Impl\Util\StackTraceUtil',
                'type'     => '->',
            ],
            [
                'file'     => '/my_work/apm-agent-php/agent/php/ElasticApm/Impl/InferredSpansBuilder.php',
                'line'     => 98,
                'function' => 'captureInClassicFormatExcludeElasticApm',
                'class'    => 'Elastic\Apm\Impl\Util\StackTraceUtil',
                'type'     => '->',
            ],
            [
                'file'     => '/my_work/apm-agent-php/agent/php/ElasticApm/Impl/InferredSpansManager.php',
                'line'     => 196,
                'function' => 'captureStackTrace',
                'class'    => 'Elastic\Apm\Impl\InferredSpansBuilder',
                'type'     => '->',
            ],
            [
                'function' => 'handleAutomaticCapturing',
                'class'    => 'Elastic\Apm\Impl\InferredSpansManager',
                'type'     => '->',
            ],
            [
                'file'     => '/var/www/html/sleep.php',
                'line'     => 6,
                'function' => 'sleep',
            ],
            [
                'file'     => '/var/www/html/sleep.php',
                'line'     => 10,
                'function' => 'coolsleep',
            ],
        ];
        /** @var ClassicFormatStackTraceFrame[] $expectedOutput */
        $expectedOutput = [
            new ClassicFormatStackTraceFrame(
                null /* <- file */,
                null /* <- line */,
                null /* <- class */,
                null /* <- isStaticMethod */,
                'sleep' /* <- function */
            ),
            new ClassicFormatStackTraceFrame(
                '/var/www/html/sleep.php' /* <- file */,
                6 /* <- line */,
                null /* <- class */,
                null /* <- isStaticMethod */,
                'coolsleep' /* <- function */
            ),
            new ClassicFormatStackTraceFrame(
                '/var/www/html/sleep.php' /* <- file */,
                10 /* <- line */
            ),
        ];

        if ($addDummyLocationToSleepFunc) {
            $inputFrame =& $input[3];
            self::assertSame('handleAutomaticCapturing', $inputFrame['function']);
            self::assertArrayNotHasKey('file', $inputFrame);
            self::assertArrayNotHasKey('line', $inputFrame);
            $inputFrame['file'] = 'dummy_file_for_internal_func_sleep.php';
            $inputFrame['line'] = 543;
            $expectedOutputFrame =& $expectedOutput[0];
            self::assertSame('sleep', $expectedOutputFrame->function);
            self::assertNull($expectedOutputFrame->file);
            self::assertNull($expectedOutputFrame->line);
            $expectedOutputFrame->file = 'dummy_file_for_internal_func_sleep.php';
            $expectedOutputFrame->line = 543;
        }

        $actualOutput = self::stackTraceUtil()->convertCaptureToClassicFormat(
            $input,
            3 /* <- offset */,
            null /* <- maxNumberOfFrames */,
            false /* <- keepElasticApmFrames */,
            false /* <- includeArgs */,
            false /* <- includeThisObj */
        );

        self::assertSameClassicFormatStackTraces($expectedOutput, $actualOutput);
    }
}
