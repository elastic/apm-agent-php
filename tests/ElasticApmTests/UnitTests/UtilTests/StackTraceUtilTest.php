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
use Elastic\Apm\Impl\StackTraceFrame;
use Elastic\Apm\Impl\Util\ArrayUtil;
use Elastic\Apm\Impl\Util\ClassicFormatStackTraceFrameOld;
use Elastic\Apm\Impl\Util\PhpFormatStackTraceFrame;
use Elastic\Apm\Impl\Util\StackTraceFrameBase;
use Elastic\Apm\Impl\Util\StackTraceUtil;
use Elastic\Apm\Impl\Util\RangeUtil;
use Elastic\Apm\Impl\Util\TextUtil;
use ElasticApmTests\ComponentTests\Util\AmbientContextForTests;
use ElasticApmTests\Util\AssertMessageStack;
use ElasticApmTests\Util\DataProviderForTestBuilder;
use ElasticApmTests\Util\IterableUtilForTests;
use ElasticApmTests\Util\MixedMap;
use ElasticApmTests\Util\SpanDto;
use ElasticApmTests\Util\StackTraceExpectations;
use ElasticApmTests\Util\TestCaseBase;

class StackTraceUtilTest extends TestCaseBase
{
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
    }

    /**
     * @dataProvider dataProviderForTestConvertClassAndMethodToFunctionName
     */
    public function testConvertClassAndMethodToFunctionName(?string $classicName, ?bool $isStaticMethod, ?string $methodName, ?string $expectedFuncName): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, AssertMessageStack::funcArgs());

        $actualFuncName = StackTraceUtil::buildFunctionNameForClassMethod($classicName, $isStaticMethod, $methodName);
        $dbgCtx->add(['actualFuncName' => $actualFuncName]);

        self::assertSame($expectedFuncName, $actualFuncName);
    }

    /**
     * @param array<string, mixed> $debugBacktraceFormatFrame
     *
     * @return PhpFormatStackTraceFrame
     */
    private static function buildPhpFormatFrame(array $debugBacktraceFormatFrame): PhpFormatStackTraceFrame
    {
        $newFrame = new PhpFormatStackTraceFrame();
        $newFrame->copyDataFromFromDebugBacktraceFrame($debugBacktraceFormatFrame);
        return $newFrame;
    }

    /**
     * @param array<string, mixed> $debugBacktraceFormatFrame
     *
     * @return ClassicFormatStackTraceFrameOld
     */
    private static function buildClassicFormatFrame(array $debugBacktraceFormatFrame): ClassicFormatStackTraceFrameOld
    {
        $newFrame = new ClassicFormatStackTraceFrameOld();
        $newFrame->copyDataFromFromDebugBacktraceFrame($debugBacktraceFormatFrame);
        return $newFrame;
    }

    /**
     * @param int  $param
     * @param int &$topFrameExpectedLine
     *
     * @return ClassicFormatStackTraceFrameOld[]
     *
     * @noinspection PhpSameParameterValueInspection
     * @noinspection PhpUnusedParameterInspection
     */
    private static function helperForTestSimpleCaptureClassicFormat(int $param, int &$topFrameExpectedLine): array
    {
        $topFrameExpectedLine = __LINE__ + 1;
        return self::stackTraceUtil()->captureInClassicFormat(/* offset */ 0, /* maxNumberOfFrames */ null, /* keepElasticApmFrames */ true, /* includeArgs */ true, /* includeThisObj */ true);
    }

    public function testSimpleCaptureClassicFormat(): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx);

        $topFrameExpectedLine = 0;
        $secondFrameExpectedLine = __LINE__ + 1;
        $actualCapturedStackTrace = self::helperForTestSimpleCaptureClassicFormat(123, /* out */ $topFrameExpectedLine);
        $dbgCtx->add(['actualCapturedStackTrace' => $actualCapturedStackTrace, 'topFrameExpectedLine' => $topFrameExpectedLine, 'secondFrameExpectedLine' => $secondFrameExpectedLine]);

        $topFrame = $actualCapturedStackTrace[0];
        self::assertSame(__FILE__, $topFrame->file);
        self::assertSame($topFrameExpectedLine, $topFrame->line);
        self::assertSame('helperForTestSimpleCaptureClassicFormat', $topFrame->function);
        self::assertSame(__CLASS__, $topFrame->class);
        self::assertTrue($topFrame->isStaticMethod);
        self::assertNull($topFrame->thisObj);
        self::assertNotNull($topFrame->args);
        self::assertCount(2, $topFrame->args);
        self::assertSame(123, $topFrame->args[0]);

        self::assertGreaterThanOrEqual(2, count($actualCapturedStackTrace));
        $secondFrame = $actualCapturedStackTrace[1];
        self::assertSame(__FILE__, $secondFrame->file);
        self::assertSame($secondFrameExpectedLine, $secondFrame->line);
        self::assertSame(__FUNCTION__, $secondFrame->function);
        self::assertSame(__CLASS__, $secondFrame->class);
        self::assertFalse($secondFrame->isStaticMethod);
        self::assertNotNull($secondFrame->thisObj);
        self::assertNotNull($secondFrame->args);
        self::assertCount(0, $secondFrame->args);
    }

    public function testSimpleConvertPhpToFromClassicFormat(): void
    {
        $phpFormatStackTrace = [
            self::buildPhpFormatFrame(
                [
                    StackTraceUtil::FILE_KEY     => 'Helper.php',
                    StackTraceUtil::LINE_KEY     => 333,  // line with call to someOtherHelperMethod()
                    StackTraceUtil::FUNCTION_KEY => 'someOtherHelperMethod',
                ]
            ),
            self::buildPhpFormatFrame(
                [
                    StackTraceUtil::FILE_KEY     => 'main.php',
                    StackTraceUtil::LINE_KEY     => 22,  // line with call to Helper::someHelperMethod()
                    StackTraceUtil::CLASS_KEY    => 'Helper',
                    StackTraceUtil::FUNCTION_KEY => 'someHelperMethod',
                ]
            ),
            self::buildPhpFormatFrame(
                [
                    StackTraceUtil::FILE_KEY     => 'bootstrap.php',
                    StackTraceUtil::LINE_KEY     => 1, // line with call to main()
                    StackTraceUtil::FUNCTION_KEY => 'main',
                ]
            ),
        ];

        $expectedPhpFormatConvertedToClassic = [
            self::buildClassicFormatFrame(
                [
                    StackTraceUtil::FUNCTION_KEY => 'someOtherHelperMethod',
                ]
            ),
            self::buildClassicFormatFrame(
                [
                    StackTraceUtil::FILE_KEY     => 'Helper.php',
                    StackTraceUtil::LINE_KEY     => 333,
                    StackTraceUtil::CLASS_KEY    => 'Helper',
                    StackTraceUtil::FUNCTION_KEY => 'someHelperMethod',
                ]
            ),
            self::buildClassicFormatFrame(
                [
                    StackTraceUtil::FILE_KEY     => 'main.php',
                    StackTraceUtil::LINE_KEY     => 22,
                    StackTraceUtil::FUNCTION_KEY => 'main',
                ]
            ),
            self::buildClassicFormatFrame(
                [
                    StackTraceUtil::FILE_KEY => 'bootstrap.php',
                    StackTraceUtil::LINE_KEY => 1,
                ]
            ),
        ];
        self::assertStackTraceMatchesExpected($expectedPhpFormatConvertedToClassic, StackTraceUtil::convertPhpToClassicFormat($phpFormatStackTrace));
        self::assertStackTraceMatchesExpected($phpFormatStackTrace, StackTraceUtil::convertClassicToPhpFormat($expectedPhpFormatConvertedToClassic));
    }

    /**
     * @param StackTraceFrame[] $expectedStackTrace
     * @param StackTraceFrame[] $actualStackTrace
     *
     * @return void
     */
    public static function assertEqualApmStackTraces(array $expectedStackTrace, array $actualStackTrace): void
    {
        SpanDto::assertStackTraceMatches(StackTraceExpectations::fromFrames($expectedStackTrace, /* allowExpectedStackTraceToBePrefix */ false), $actualStackTrace);
    }

    public function testSimpleConvertClassicToApmFormat(): void
    {
        $classicFormatStackTrace = [
            self::buildClassicFormatFrame(
                [
                    StackTraceUtil::FILE_KEY     => 'Helper2.php',
                    StackTraceUtil::LINE_KEY     => 4444,
                    StackTraceUtil::CLASS_KEY    => 'Helper2',
                    StackTraceUtil::FUNCTION_KEY => 'helper2StaticMethod',
                    StackTraceUtil::TYPE_KEY     => '::',
                ]
            ),
            self::buildClassicFormatFrame(
                [
                    StackTraceUtil::FILE_KEY     => 'Helper.php',
                    StackTraceUtil::LINE_KEY     => 333,
                    StackTraceUtil::CLASS_KEY    => 'Helper',
                    StackTraceUtil::FUNCTION_KEY => 'helperMethod',
                    StackTraceUtil::TYPE_KEY     => '->',
                ]
            ),
            self::buildClassicFormatFrame(
                [
                    StackTraceUtil::FILE_KEY     => 'main.php',
                    StackTraceUtil::LINE_KEY     => 22,
                    StackTraceUtil::FUNCTION_KEY => 'main',
                ]
            ),
            self::buildClassicFormatFrame(
                [
                    StackTraceUtil::FILE_KEY => 'bootstrap.php',
                    StackTraceUtil::LINE_KEY => 1,
                ]
            ),
        ];

        $expectedApmFormatStackTrace = [
            new StackTraceFrame('Helper2.php', 4444, null),
            new StackTraceFrame('Helper.php', 333, 'Helper2::helper2StaticMethod'),
            new StackTraceFrame('main.php', 22, 'Helper->helperMethod'),
            new StackTraceFrame('bootstrap.php', 1, 'main'),
        ];

        $actualApmFormatStackTrace = StackTraceUtil::convertClassicToApmFormat($classicFormatStackTrace);
        self::assertEqualApmStackTraces($expectedApmFormatStackTrace, $actualApmFormatStackTrace);
    }

    private static function assertOptionalPartsAsExpected(bool $includeArgs, bool $includeThisObj, StackTraceFrameBase $actualCaptureStackFrame): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, AssertMessageStack::funcArgs());

        if ($includeArgs) {
            // If requested args is always returned even for functions with no parameters in which case args will the empty array
            self::assertNotNull($actualCaptureStackFrame->args);
        } else {
            self::assertNull($actualCaptureStackFrame->args);
        }

        if (!$includeThisObj) {
            self::assertNull($actualCaptureStackFrame->thisObj);
        }
    }

    /**
     * @return PhpFormatStackTraceFrame[]
     */
    private static function captureInPhpFormat(?int $maxNumberOfFrames, bool $includeArgs, bool $includeThisObj): array
    {
        return self::stackTraceUtil()->captureInPhpFormat(/* offset */ 1, $maxNumberOfFrames, $includeArgs, $includeThisObj);
    }

    /**
     * @return ClassicFormatStackTraceFrameOld[]
     */
    private static function captureInClassicFormat(?int $maxNumberOfFrames, bool $includeArgs, bool $includeThisObj): array
    {
        return self::stackTraceUtil()->captureInClassicFormat(/* offset */ 1, $maxNumberOfFrames, /* includeElasticApmFrames */ true, $includeArgs, $includeThisObj);
    }

    /**
     * @return iterable<string, array{bool, bool, ?int}>
     */
    public function dataProviderForTestCapture(): iterable
    {
        /**
         * @return iterable<array<string, mixed>>
         */
        $generator = function (): iterable {
            foreach ([null, 0, 1, 2, 3, self::VERY_LARGE_STACK_TRACE_SIZE_LIMIT] as $maxNumberOfFrames) {
                foreach (IterableUtilForTests::ALL_BOOL_VALUES as $includeArgs) {
                    foreach (IterableUtilForTests::ALL_BOOL_VALUES as $includeThisObj) {
                        yield [
                            'maxNumberOfFrames' => $maxNumberOfFrames,
                            'includeArgs'       => $includeArgs,
                            'includeThisObj'    => $includeThisObj,
                        ];
                    }
                }
            }
        };
        return DataProviderForTestBuilder::keyEachDataSetWithDbgDesc($generator);
    }

    /**
     * @dataProvider dataProviderForTestCapture
     */
    public function testCapturePhpFormat(?int $maxNumberOfFrames, bool $includeArgs, bool $includeThisObj): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, AssertMessageStack::funcArgs());

        /** @var null|PhpFormatStackTraceFrame[] $actualCapturedStackTrace */
        $actualCapturedStackTrace = null;
        /** @var array<string, mixed>[] $dbgDebugBackTrace */
        $dbgDebugBackTrace = null;
        /** @var ?int $expectedLine */
        $expectedLine = null;
        $callCaptureInPhpFormat = function () use ($includeArgs, $includeThisObj, $maxNumberOfFrames, &$actualCapturedStackTrace, &$dbgDebugBackTrace, &$expectedLine): void {
            $dbgDebugBackTrace = debug_backtrace(($includeArgs ? 0 : DEBUG_BACKTRACE_IGNORE_ARGS) | ($includeThisObj ? DEBUG_BACKTRACE_PROVIDE_OBJECT : 0), $maxNumberOfFrames ?? 0);
            $actualCapturedStackTrace = self::captureInPhpFormat($maxNumberOfFrames, $includeArgs, $includeThisObj);
            $expectedLine = __LINE__ - 1;
        };
        $callCaptureInPhpFormat();
        $expectedLineNextFrame = __LINE__ - 1;
        $dbgCtx->add(['expectedLineNextFrame' => $expectedLineNextFrame, 'actualCapturedStackTrace' => $actualCapturedStackTrace, 'dbgDebugBackTrace' => $dbgDebugBackTrace]);
        $dbgCtx->add(['expectedLine' => $expectedLine]);

        $expectedArgs = func_get_args();
        $dbgCtx->add(['expectedArgs' => $expectedArgs]);

        self::assertNotNull($actualCapturedStackTrace);
        if ($maxNumberOfFrames !== null) {
            self::assertLessThanOrEqual($maxNumberOfFrames, count($actualCapturedStackTrace));
            // There is this function and $callCaptureInPhpFormat closure
            // so there should be at least two frames before max is applied
            if ($maxNumberOfFrames <= 2) {
                self::assertCount($maxNumberOfFrames, $actualCapturedStackTrace);
            }
        }

        $dbgCtx->pushSubScope();
        for ($i = 0; $i < count($actualCapturedStackTrace); ++$i) {
            $dbgCtx->clearCurrentSubScope(['i' => $i]);
            $frame = $actualCapturedStackTrace[$i];
            $dbgCtx->add(['frame' => $frame]);
            if ($i !== 0) {
                self::assertOptionalPartsAsExpected($includeArgs, $includeThisObj, $frame);
            }
            switch ($i) {
                case 0:
                    // Top frame in PHP stack trace format should contain only source location data file and line
                    self::assertSame(__FILE__, $frame->file);
                    self::assertSame($expectedLine, $frame->line);
                    self::assertNull($frame->function);
                    self::assertNull($frame->class);
                    self::assertNull($frame->isStaticMethod);
                    self::assertNull($frame->thisObj);
                    self::assertNull($frame->args);
                    break;
                case 1:
                    // Middle frame is for the body of $callCaptureInPhpFormat closure
                    self::assertSame(__FILE__, $frame->file);
                    self::assertSame($expectedLineNextFrame, $frame->line);
                    self::assertNotNull($frame->function);
                    self::assertTrue(TextUtil::contains($frame->function, 'closure'));
                    self::assertSame(__CLASS__, $frame->class);
                    self::assertFalse($frame->isStaticMethod);
                    // A closure inherits $this from the surrounding scope
                    if ($includeThisObj) {
                        self::assertSame($this, $frame->thisObj);
                    }
                    if ($includeArgs) {
                        self::assertNotNull($frame->args);
                        self::assertCount(0, $frame->args);
                    }
                    break;
                case 2:
                    self::assertNotEqualsEx(__FILE__, $frame->file);
                    self::assertNotNull($frame->line);
                    self::assertSame(__FUNCTION__, $frame->function);
                    self::assertSame(__CLASS__, $frame->class);
                    if ($includeThisObj) {
                        self::assertSame($this, $frame->thisObj);
                    }
                    if ($includeArgs) {
                        self::assertNotNull($frame->args);
                        self::assertEqualLists($expectedArgs, $frame->args);
                    }
                    self::assertNotNull($frame->isStaticMethod);
                    break;
            }
        }
        $dbgCtx->popSubScope();
    }

    /**
     * @dataProvider dataProviderForTestCapture
     */
    public function testCaptureClassicFormat(?int $maxNumberOfFrames, bool $includeArgs, bool $includeThisObj): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, AssertMessageStack::funcArgs());

        $dbgDebugBackTrace = debug_backtrace(($includeArgs ? 0 : DEBUG_BACKTRACE_IGNORE_ARGS) | ($includeThisObj ? DEBUG_BACKTRACE_PROVIDE_OBJECT : 0), $maxNumberOfFrames ?? 0);
        $dbgCtx->add(['dbgDebugBackTrace' => $dbgDebugBackTrace]);
        $actualCapturedStackTrace = self::captureInClassicFormat($maxNumberOfFrames, $includeArgs, $includeThisObj);
        $expectedLine = __LINE__ - 1;
        $expectedArgs = func_get_args();
        $dbgCtx->add(['actualCapturedStackTrace' => $actualCapturedStackTrace, 'expectedLine' => $expectedLine, 'expectedArgs' => $expectedArgs]);

        self::assertNotNull($actualCapturedStackTrace);
        if ($maxNumberOfFrames === null) {
            // There is this function and PHPUnit function that called it
            // so there should be at least two frames since max is not applied
            self::assertGreaterThanOrEqual(2, count($actualCapturedStackTrace));
        } else {
            self::assertLessThanOrEqual($maxNumberOfFrames, count($actualCapturedStackTrace));
            // There is this function and PHPUnit function that called it
            // so there should be at least two frames before max is applied
            if ($maxNumberOfFrames <= 2) {
                self::assertCount($maxNumberOfFrames, $actualCapturedStackTrace);
            }
        }

        if ($maxNumberOfFrames === 0) {
            return;
        }

        $topFrame = $actualCapturedStackTrace[0];
        self::assertSame(__FILE__, $topFrame->file);
        self::assertSame($expectedLine, $topFrame->line);
        self::assertSame(__FUNCTION__, $topFrame->function);
        self::assertSame(__CLASS__, $topFrame->class);
        self::assertFalse($topFrame->isStaticMethod);

        if ($includeThisObj) {
            self::assertNotNull($topFrame->thisObj);
            self::assertSame($this, $topFrame->thisObj);
        } else {
            self::assertNull($topFrame->thisObj);
        }

        if ($includeArgs) {
            self::assertNotNull($topFrame->args);
            self::assertEqualAsSets($expectedArgs, $topFrame->args);
        } else {
            self::assertNull($topFrame->args);
        }
    }

    private const MAX_FUNC_DEPTH_KEY = 'max_func_depth';
    private const INCLUDE_ARGS_KEY = 'include_args';
    private const INCLUDE_THIS_OBJ_KEY = 'include_this_obj';
    private const MAX_NUMBER_OF_FRAMES_KEY = 'max_number_of_frames';
    private const EXPECTED_CAPTURED_CLASSIC_STACK_TRACE_TOP_KEY = 'expected_captured_classic_stack_trace_top';
    private const EXPECTED_CONVERTED_CLASSIC_STACK_TRACE_TOP_KEY = 'expected_converted_classic_stack_trace_top';
    private const ACTUAL_CAPTURED_PHP_STACK_TRACE_KEY = 'actual_captured_php_stack_trace';
    private const ACTUAL_CAPTURED_CLASSIC_STACK_TRACE_KEY = 'actual_captured_classic_stack_trace';

    /**
     * @return array<string, mixed>
     */
    private static function funcImpl(?self $thisObj, int $funcDepth, MixedMap $testArgs, string $funcNameCalledFrom, bool $isFuncCalledFromStatic, int $lineNumberWithCallToFuncImpl): array
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, AssertMessageStack::funcArgs());
        $maxDepth = $testArgs->getInt(self::MAX_FUNC_DEPTH_KEY);
        $includeArgs = $testArgs->getBool(self::INCLUDE_ARGS_KEY);
        $includeThisObj = $testArgs->getBool(self::INCLUDE_THIS_OBJ_KEY);
        $maxNumberOfFrames = $testArgs->getNullableInt(self::MAX_NUMBER_OF_FRAMES_KEY);

        if ($funcDepth < $maxDepth) {
            self::assertNotNull($thisObj);
            switch ($funcDepth) {
                case 1:
                    $result = $thisObj->funcB('funcA', $funcDepth + 1, $testArgs);
                    $expectedCapturedLine = __LINE__ - 1;
                    $expectedConvertedLine = $expectedCapturedLine;
                    break;
                case 2:
                    $result = $thisObj->funcC('funcB', $funcDepth + 1, $testArgs);
                    $expectedCapturedLine = __LINE__ - 1;
                    $expectedConvertedLine = $expectedCapturedLine;
                    break;
                default:
                    self::fail('Unexpected $funcDepth value');
            }
        } else {
            $result = [];
            $result[self::ACTUAL_CAPTURED_PHP_STACK_TRACE_KEY] = self::captureInPhpFormat($maxNumberOfFrames, $includeArgs, $includeThisObj);
            $expectedConvertedLine = __LINE__ - 1;
            $result[self::ACTUAL_CAPTURED_CLASSIC_STACK_TRACE_KEY] = self::captureInClassicFormat($maxNumberOfFrames, $includeArgs, $includeThisObj);
            $expectedCapturedLine = __LINE__ - 1;
            $result[self::EXPECTED_CONVERTED_CLASSIC_STACK_TRACE_TOP_KEY] = [];
            $result[self::EXPECTED_CAPTURED_CLASSIC_STACK_TRACE_TOP_KEY] = [];
        }

        $currentFrame = new ClassicFormatStackTraceFrameOld();
        $currentFrame->file = __FILE__;
        $currentFrame->line = $expectedConvertedLine;
        $currentFrame->function = __FUNCTION__;
        $currentFrame->class = __CLASS__;
        $currentFrame->isStaticMethod = true;
        if ($includeArgs) {
            $currentFrame->args = func_get_args();
        }

        $callingFuncFrame = new ClassicFormatStackTraceFrameOld();
        $callingFuncFrame->file = __FILE__;
        $callingFuncFrame->line = $lineNumberWithCallToFuncImpl;
        $callingFuncFrame->function = $funcNameCalledFrom;
        $callingFuncFrame->class = __CLASS__;
        $callingFuncFrame->isStaticMethod = $isFuncCalledFromStatic;

        /** @var ClassicFormatStackTraceFrameOld[] $expectedConvertedClassicStackTraceTop */
        $expectedConvertedClassicStackTraceTop = &$result[self::EXPECTED_CONVERTED_CLASSIC_STACK_TRACE_TOP_KEY];
        $expectedConvertedClassicStackTraceTop[] = $currentFrame;
        $expectedConvertedClassicStackTraceTop[] = $callingFuncFrame;
        unset($expectedConvertedClassicStackTraceTop);

        /** @var ClassicFormatStackTraceFrameOld[] $expectedCapturedClassicStackTraceTop */
        $expectedCapturedClassicStackTraceTop = &$result[self::EXPECTED_CAPTURED_CLASSIC_STACK_TRACE_TOP_KEY];
        $currentFrameAdapted = clone $currentFrame;
        $currentFrameAdapted->line = $expectedCapturedLine;
        $expectedCapturedClassicStackTraceTop[] = $currentFrameAdapted;
        $expectedCapturedClassicStackTraceTop[] = $callingFuncFrame;
        unset($expectedCapturedClassicStackTraceTop);
        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function funcA(MixedMap $testArgs): array
    {
        return self::funcImpl($this, /* funcDepth */ 1, $testArgs, __FUNCTION__, /* isStatic */ false, __LINE__);
    }

    /**
     * @return array<string, mixed>
     * @noinspection PhpSameParameterValueInspection
     */
    private function funcB(string $calledFromFunc, int $funcDepth, MixedMap $testArgs): array
    {
        self::assertSame('funcA', $calledFromFunc);
        return self::funcImpl($this, $funcDepth, $testArgs, __FUNCTION__, /* isStatic */ false, __LINE__);
    }

    /**
     * @return array<string, mixed>
     * @noinspection PhpSameParameterValueInspection
     */
    private static function funcC(string $calledFromFunc, int $funcDepth, MixedMap $testArgs): array
    {
        self::assertSame('funcB', $calledFromFunc);
        return self::funcImpl(/* thisObj */ null, $funcDepth, $testArgs, __FUNCTION__, /* isStatic */ true, __LINE__);
    }

    private static function isLocationProperty(string $propName): bool
    {
        return $propName === 'file' || $propName === 'line';
    }

    /**
     * @param PhpFormatStackTraceFrame[]        $phpFormatStackTrace
     * @param ClassicFormatStackTraceFrameOld[] $classicFormatStackTrace
     *
     * @return void
     */
    public static function assertPhpFormatMatchesClassic(array $phpFormatStackTrace, array $classicFormatStackTrace): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, AssertMessageStack::funcArgs());

        self::assertSameCount($phpFormatStackTrace, $classicFormatStackTrace);

        $dbgCtx->pushSubScope();
        foreach (RangeUtil::generateUpTo(count($classicFormatStackTrace)) as $frameIndex) {
            $dbgCtx->clearCurrentSubScope(['frameIndex' => $frameIndex]);
            $classicCurrentFrame = $classicFormatStackTrace[$frameIndex];
            $dbgCtx->add(['classicCurrentFrame' => $classicCurrentFrame]);
            $phpFrameWithLocationData = $phpFormatStackTrace[$frameIndex];
            $dbgCtx->add(['phpFrameWithLocationData' => $phpFrameWithLocationData]);
            $phpFrameWithNonLocationData = $frameIndex + 1 < count($phpFormatStackTrace) ? $phpFormatStackTrace[$frameIndex + 1] : null;
            $dbgCtx->pushSubScope();
            foreach (get_object_vars($classicCurrentFrame) as $propName => $classicVal) {
                if ($classicVal === null) {
                    continue;
                }
                $dbgCtx->clearCurrentSubScope(['propName' => $propName, 'classicVal' => $classicVal]);
                if (self::isLocationProperty($propName)) {
                    self::assertSame($classicVal, $phpFrameWithLocationData->{$propName});
                } else {
                    if ($phpFrameWithNonLocationData !== null) {
                        self::assertSame($classicVal, $phpFrameWithNonLocationData->{$propName});
                    }
                }
            }
            $dbgCtx->popSubScope();
        }
        $dbgCtx->popSubScope();
    }

    /**
     * @template TStackFrameFormat of StackTraceFrameBase
     *
     * @param StackTraceFrameBase[] $expected
     * @param StackTraceFrameBase[] $actual
     *
     * @return void
     *
     * @phpstan-param TStackFrameFormat[] $expected
     * @phpstan-param TStackFrameFormat[] $actual
     *
     * @noinspection PhpUndefinedClassInspection
     */
    private static function assertStackTraceMatchesExpected(array $expected, array $actual): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, AssertMessageStack::funcArgs());
        self::assertSameCount($expected, $actual);
        $dbgCtx->pushSubScope();
        foreach (RangeUtil::generateUpTo(count($expected)) as $frameIndex) {
            $dbgCtx->clearCurrentSubScope(['frameIndex' => $frameIndex]);
            $expectedStackFrame = $expected[$frameIndex];
            $actualStackFrame = $actual[$frameIndex];
            $dbgCtx->add(['expectedStackFrame' => $expectedStackFrame, 'actualStackFrame' => $actualStackFrame]);
            $dbgCtx->pushSubScope();
            foreach (get_object_vars($expectedStackFrame) as $expectedKey => $expectedVal) {
                if ($expectedVal === null) {
                    continue;
                }
                $dbgCtx->clearCurrentSubScope(['expectedKey' => $expectedKey, 'expectedVal' => $expectedVal]);
                self::assertSame($expectedVal, $actualStackFrame->{$expectedKey});
            }
            $dbgCtx->popSubScope();
        }
        $dbgCtx->popSubScope();
    }

    /**
     * @template     TStackFrameFormat of StackTraceFrameBase
     *
     * @param string              $dbgStackTraceDesc
     * @param TStackFrameFormat[] $stackTrace
     * @param bool                $isActual
     * @param MixedMap            $testArgs
     *
     * @noinspection PhpUndefinedClassInspection
     * @noinspection PhpUnusedParameterInspection
     */
    private static function assertValidStackTrace(string $dbgStackTraceDesc, array $stackTrace, bool $isActual, MixedMap $testArgs): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, AssertMessageStack::funcArgs());
        $includeArgs  = $testArgs->getBool(self::INCLUDE_ARGS_KEY);
        $includeThisObj = $testArgs->getBool(self::INCLUDE_THIS_OBJ_KEY);
        $dbgCtx->pushSubScope();
        foreach (RangeUtil::generateUpTo(count($stackTrace)) as $i) {
            $dbgCtx->clearCurrentSubScope(['i' => $i]);
            $frame = $stackTrace[$i];
            $dbgCtx->add(['frame' => $frame]);
            $maybeLocationDataOnlyFrame = ($stackTrace[0] instanceof PhpFormatStackTraceFrame) ? ($i === 0) : ($i === (count($stackTrace) - 1));
            if ($isActual && !$maybeLocationDataOnlyFrame) {
                self::assertOptionalPartsAsExpected($includeArgs, $includeThisObj, $frame);
            }
        }
        $dbgCtx->popSubScope();
    }

    /**
     * @template TStackFrameFormat of StackTraceFrameBase
     *
     * @param string              $testFuncName
     * @param string              $dbgStackTraceDesc
     * @param TStackFrameFormat[] $stackTrace
     * @param int                 $maxDepth
     *
     * @noinspection PhpUndefinedClassInspection
     * @noinspection PhpUnusedParameterInspection
     */
    private static function assertStackTraceTopAsExpected(string $testFuncName, string $dbgStackTraceDesc, array $stackTrace, int $maxDepth): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, AssertMessageStack::funcArgs());
        self::assertGreaterThan(0, count($stackTrace));
        $isPhpFormat = $stackTrace[0] instanceof PhpFormatStackTraceFrame;
        $dbgCtx->add(['isPhpFormat' => $isPhpFormat]);

        /** @var ?int $funcAIndex */
        $funcAIndex = null;
        /** @var ?int $funcBIndex */
        $funcBIndex = null;
        /** @var ?int $funcCIndex */
        $funcCIndex = null;

        self::assertInRangeInclusive(1, $maxDepth, 3);
        switch ($maxDepth) {
            case 1:
                $funcAIndex = 1;
                break;
            case 2:
                $funcBIndex = 1;
                $funcAIndex = 3;
                break;
            case 3:
                $funcCIndex = 1;
                $funcBIndex = 3;
                $funcAIndex = 5;
                break;
            default:
                self::fail(LoggableToString::convert(['maxDepth' => $maxDepth, 'stackTrace' => $stackTrace]));
        }

        $testFuncIndex = (count($stackTrace) > $funcAIndex + 1) ? $funcAIndex + 1 : null;

        $expectedFuncNames = [
            [$funcAIndex, 'funcA', /* isStatic */ false, null, null],
            [$funcBIndex, 'funcB', /* isStatic */ false, 'funcA', 2],
            [$funcCIndex, 'funcC', /* isStatic */ true, 'funcB', 3],
            [$testFuncIndex, $testFuncName, /* isStatic */ false, null, null],
        ];
        $dbgCtx->pushSubScope();
        foreach ($expectedFuncNames as [$index, $funcName, $isStatic, $expectedCalledFromFunc, $expectedFuncDepth]) {
            $dbgCtx->clearCurrentSubScope(['index' => $index, 'funcName' => $funcName, 'isStatic' => $isStatic, 'expectedCalledFromFunc' => $expectedCalledFromFunc]);
            $dbgCtx->add(['expectedFuncDepth' => $expectedFuncDepth]);
            if ($index === null) {
                continue;
            }
            if ($isPhpFormat) {
                ++$index;
            }
            if (!($index < count($stackTrace))) {
                continue;
            }
            /** @var StackTraceFrameBase $stackTraceFrame */
            $stackTraceFrame = $stackTrace[$index];
            $dbgCtx->add(['stackTraceFrame' => $stackTraceFrame]);
            self::assertSame($funcName, $stackTraceFrame->function);
            self::assertSame($isStatic, $stackTraceFrame->isStaticMethod);
            if (($args = $stackTraceFrame->args) !== null) {
                if ($expectedCalledFromFunc !== null) {
                    $calledFromFuncArgNum = 0;
                    self::assertSameValueInArray($calledFromFuncArgNum, $expectedCalledFromFunc, $args);
                }
                if ($expectedFuncDepth !== null) {
                    $funcDepthArgNum = 1;
                    self::assertSameValueInArray($funcDepthArgNum, $expectedFuncDepth, $args);
                }
            }
        }
        $dbgCtx->popSubScope();

        $dbgCtx->pushSubScope();
        foreach ([0, 2, 4] as $expectedFuncImplIndex) {
            $funcImplClosestToBottom = $funcAIndex - 1;
            $dbgCtx->clearCurrentSubScope(['expectedFuncImplIndex' => $expectedFuncImplIndex, 'funcImplClosestToBottom' => $funcImplClosestToBottom]);
            if ($isPhpFormat) {
                ++$expectedFuncImplIndex;
                ++$funcImplClosestToBottom;
            }
            if ($expectedFuncImplIndex < count($stackTrace) && $expectedFuncImplIndex <= $funcImplClosestToBottom) {
                self::assertSame('funcImpl', $stackTrace[$expectedFuncImplIndex]->function);
            }
        }
        $dbgCtx->popSubScope();
    }

    /**
     * @return iterable<string, array{MixedMap}>
     */
    public function dataProviderForTestConvertPhpFormatToClassic(): iterable
    {
        /**
         * @return iterable<array<string, mixed>>
         */
        $generator = function (): iterable {
            foreach (RangeUtil::generateFromToIncluding(1, 3) as $maxDepth) {
                foreach ([null, 1, $maxDepth * 2, self::VERY_LARGE_STACK_TRACE_SIZE_LIMIT] as $maxNumberOfFrames) {
                    foreach (IterableUtilForTests::ALL_BOOL_VALUES as $includeArgs) {
                        foreach (IterableUtilForTests::ALL_BOOL_VALUES as $includeThisObj) {
                            yield [
                                self::MAX_FUNC_DEPTH_KEY       => $maxDepth,
                                self::MAX_NUMBER_OF_FRAMES_KEY => $maxNumberOfFrames,
                                self::INCLUDE_ARGS_KEY         => $includeArgs,
                                self::INCLUDE_THIS_OBJ_KEY     => $includeThisObj,
                            ];
                        }
                    }
                }
            }
        };
        return DataProviderForTestBuilder::convertEachDataSetToMixedMapAndAddDesc($generator);
    }

    private static function resetNonLocationProperties(StackTraceFrameBase $frame): void
    {
        $frame->class = null;
        $frame->function = null;
        $frame->isStaticMethod = null;
        $frame->thisObj = null;
        $frame->args = null;
    }

    /**
     * @dataProvider dataProviderForTestConvertPhpFormatToClassic
     */
    public function testPhpVsClassicFormats(MixedMap $testArgs): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, AssertMessageStack::funcArgs());

        $maxDepth = $testArgs->getInt(self::MAX_FUNC_DEPTH_KEY);
        $maxNumberOfFrames = $testArgs->getNullableInt(self::MAX_NUMBER_OF_FRAMES_KEY);
        $includeArgs = $testArgs->getBool(self::INCLUDE_ARGS_KEY);
        $includeThisObj = $testArgs->getBool(self::INCLUDE_THIS_OBJ_KEY);

        $result = $this->funcA($testArgs);
        $funcACallLineNumber = __LINE__ - 1;

        /** @var PhpFormatStackTraceFrame[] $actualCapturedPhp */
        $actualCapturedPhp = $result[self::ACTUAL_CAPTURED_PHP_STACK_TRACE_KEY];
        /** @var ClassicFormatStackTraceFrameOld[] $actualCapturedClassic */
        $actualCapturedClassic = $result[self::ACTUAL_CAPTURED_CLASSIC_STACK_TRACE_KEY];

        $isLimitEffective = $maxNumberOfFrames !== null && $maxNumberOfFrames !== self::VERY_LARGE_STACK_TRACE_SIZE_LIMIT;

        $phpStackTraceToHere = self::captureInPhpFormat($maxNumberOfFrames, $includeArgs, $includeThisObj);
        self::assertArrayHasKey(0, $phpStackTraceToHere);
        $phpStackTraceToHere[0]->line = $funcACallLineNumber;
        $classicStackTraceToHere = self::captureInClassicFormat($maxNumberOfFrames, $includeArgs, $includeThisObj);
        $classicStackTraceToHere[0]->line = $funcACallLineNumber;
        $dbgCtx->add(['classicStackTraceToHere' => $classicStackTraceToHere]);

        /** @var PhpFormatStackTraceFrame[] $expectedConvertedClassicTop */
        $expectedConvertedClassicTop = $result[self::EXPECTED_CONVERTED_CLASSIC_STACK_TRACE_TOP_KEY];
        /** @var ClassicFormatStackTraceFrameOld[] $expectedConvertedClassic */
        $expectedConvertedClassic = array_merge($expectedConvertedClassicTop, $classicStackTraceToHere);
        if ($isLimitEffective) {
            $expectedConvertedClassic = array_slice($expectedConvertedClassic, 0, $maxNumberOfFrames);
        }

        /** @var ClassicFormatStackTraceFrameOld[] $expectedCapturedClassicTop */
        $expectedCapturedClassicTop = $result[self::EXPECTED_CAPTURED_CLASSIC_STACK_TRACE_TOP_KEY];
        /** @var ClassicFormatStackTraceFrameOld[] $expectedCapturedClassic */
        $expectedCapturedClassic = array_merge($expectedCapturedClassicTop, $classicStackTraceToHere);
        if ($isLimitEffective) {
            $expectedCapturedClassic = array_slice($expectedCapturedClassic, 0, $maxNumberOfFrames);
        }

        $allStackTraces = [
            'actualCapturedPhp'        => [$actualCapturedPhp, /* isActual */ true],
            'actualCapturedClassic'    => [$actualCapturedClassic, /* isActual */ true],
            'expectedCapturedClassic'  => [$expectedCapturedClassic, /* isActual */ false],
            'expectedConvertedClassic' => [$expectedConvertedClassic, /* isActual */ false],
        ];
        $dbgCtx->add(['allStackTraces' => $allStackTraces]);
        $dbgCtx->pushSubScope();
        foreach ($allStackTraces as $dbgStackTraceDesc => [$stackTrace, $isActual]) {
            $dbgCtx->clearCurrentSubScope(['dbgStackTraceDesc' => $dbgStackTraceDesc, 'stackTrace' => $stackTrace, 'isActual' => $isActual]);
            $isPhpFormat = $stackTrace[0] instanceof PhpFormatStackTraceFrame;
            $expectedStackTraceCount = $isLimitEffective
                ? $maxNumberOfFrames
                : (count($isPhpFormat ? $phpStackTraceToHere : $classicStackTraceToHere) + $maxDepth * 2);
            $dbgCtx->add(['expectedStackTraceCount' => $expectedStackTraceCount]);
            self::assertCount($expectedStackTraceCount, $stackTrace);
            self::assertValidStackTrace($dbgStackTraceDesc, $stackTrace, $isActual, $testArgs);
            self::assertStackTraceTopAsExpected(__FUNCTION__, $dbgStackTraceDesc, $stackTrace, $maxDepth);
        }
        $dbgCtx->popSubScope();

        self::assertStackTraceMatchesExpected($expectedCapturedClassic, $actualCapturedClassic);
        $expectedConvertedClassicAdapted = $expectedConvertedClassic;
        if ($isLimitEffective) {
            $bottomFrame = $expectedConvertedClassicAdapted[count($expectedConvertedClassicAdapted) - 1];
            self::resetNonLocationProperties($bottomFrame);
        }
        self::assertStackTraceMatchesExpected($expectedConvertedClassicAdapted, StackTraceUtil::convertPhpToClassicFormat($actualCapturedPhp));

        $actualCapturedPhpAdapted = $actualCapturedPhp;
        $actualCapturedPhpAdapted[0] = clone $actualCapturedPhpAdapted[0];
        $actualCapturedPhpAdapted[0]->line = $expectedCapturedClassic[0]->line;
        self::assertPhpFormatMatchesClassic($actualCapturedPhpAdapted, $expectedCapturedClassic);
        self::assertPhpFormatMatchesClassic($actualCapturedPhpAdapted, $actualCapturedClassic);
        self::assertStackTraceMatchesExpected($actualCapturedPhpAdapted, StackTraceUtil::convertClassicToPhpFormat($actualCapturedClassic));
    }

    public static function buildFunctionNameForClassMethod(string $className, string $methodName): string
    {
        return $className . '->' . $methodName;
    }

    public static function buildFunctionNameForClassStaticMethod(string $className, string $methodName): string
    {
        return $className . '::' . $methodName;
    }

    private const MANUALLY_BUILT_STACK_TRACE_COUNT = 4;
    private const NUMBER_OF_STACK_FRAMES_TO_SKIP_KEY = 'number_of_stack_frames_to_skip';

    /**
     * @return iterable<string, array{MixedMap}>
     */
    public function dataProviderForTestCaptureInApmFormat(): iterable
    {
        $result = (new DataProviderForTestBuilder())
            ->addKeyedDimensionAllValuesCombinable(self::NUMBER_OF_STACK_FRAMES_TO_SKIP_KEY, DataProviderForTestBuilder::rangeUpTo(self::MANUALLY_BUILT_STACK_TRACE_COUNT))
            ->build();

        return DataProviderForTestBuilder::convertEachDataSetToMixedMap($result);
    }

    /**
     * @param int                $numberOfStackFramesToSkip
     * @param int                $calledFromLineNumber
     * @param StackTraceFrame[] &$manuallyBuiltStackTrace
     *
     * @return StackTraceFrame[]
     */
    private function helper1ForTestCaptureInApmFormat(int $numberOfStackFramesToSkip, int $calledFromLineNumber, array &$manuallyBuiltStackTrace): array
    {
        $manuallyBuiltStackTrace[] = new StackTraceFrame(__FILE__, $calledFromLineNumber, self::buildFunctionNameForClassMethod(__CLASS__, __FUNCTION__));
        return self::helper2ForTestCaptureInApmFormat($numberOfStackFramesToSkip, __LINE__, /* ref */ $manuallyBuiltStackTrace);
    }

    /**
     * @param int                $numberOfStackFramesToSkip
     * @param int                $calledFromLineNumber
     * @param StackTraceFrame[] &$manuallyBuiltStackTrace
     *
     * @return StackTraceFrame[]
     */
    private static function helper2ForTestCaptureInApmFormat(int $numberOfStackFramesToSkip, int $calledFromLineNumber, array &$manuallyBuiltStackTrace): array
    {
        $manuallyBuiltStackTrace[] = new StackTraceFrame(__FILE__, $calledFromLineNumber, self::buildFunctionNameForClassStaticMethod(__CLASS__, __FUNCTION__));
        $func = function () use ($numberOfStackFramesToSkip, &$manuallyBuiltStackTrace): array {
            $manuallyBuiltStackTrace[] = new StackTraceFrame(DUMMY_FUNC_FOR_TESTS_WITHOUT_NAMESPACE_CALLABLE_FILE_NAME, DUMMY_FUNC_FOR_TESTS_WITHOUT_NAMESPACE_CALLABLE_LINE_NUMBER, __FUNCTION__);
            self::assertCount(self::MANUALLY_BUILT_STACK_TRACE_COUNT, $manuallyBuiltStackTrace);
            return self::stackTraceUtil()->captureInApmFormat($numberOfStackFramesToSkip + 1);
        };
        $manuallyBuiltStackTrace[] = new StackTraceFrame(__FILE__, __LINE__ + 1, 'dummyFuncForTestsWithoutNamespace');
        return dummyFuncForTestsWithoutNamespace($func);
    }

    /**
     * @dataProvider dataProviderForTestCaptureInApmFormat
     */
    public function testCaptureInApmFormat(MixedMap $testArgs): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, AssertMessageStack::funcArgs());
        $numberOfStackFramesToSkip = $testArgs->getInt(self::NUMBER_OF_STACK_FRAMES_TO_SKIP_KEY);

        $manuallyBuiltStackTrace = [];
        $actualStackTrace = $this->helper1ForTestCaptureInApmFormat($numberOfStackFramesToSkip, __LINE__, /* ref */ $manuallyBuiltStackTrace);
        self::assertCount(self::MANUALLY_BUILT_STACK_TRACE_COUNT, $manuallyBuiltStackTrace);
        $manuallyBuiltStackTrace = array_reverse($manuallyBuiltStackTrace);
        $dbgCtx->add(['actualStackTrace' => $actualStackTrace, 'manuallyBuiltStackTrace' => $manuallyBuiltStackTrace]);
        self::assertGreaterThanOrEqual($numberOfStackFramesToSkip, count($manuallyBuiltStackTrace));
        /** @var StackTraceFrame[] $expectedStackTracePrefix */
        $expectedStackTracePrefix = array_slice($manuallyBuiltStackTrace, /* offset */ $numberOfStackFramesToSkip);
        if (!ArrayUtil::isEmpty($expectedStackTracePrefix)) {
            /**
             * top frame should not have 'function' property because StackTraceUtil::captureInApmFormat assumees that the function being called is from Elastic APM API
             */
            $expectedStackTracePrefix[0]->function = null;
        }
        SpanDto::assertStackTraceMatches(StackTraceExpectations::fromFrames($expectedStackTracePrefix, /* allowToBePrefixOfActual */ true), $actualStackTrace);
    }
}
