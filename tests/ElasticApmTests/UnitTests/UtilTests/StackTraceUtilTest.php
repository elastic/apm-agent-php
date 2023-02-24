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
use Elastic\Apm\Impl\Util\ClassicFormatStackTraceFrame;
use Elastic\Apm\Impl\Util\PhpFormatStackTraceFrame;
use Elastic\Apm\Impl\Util\StackTraceFrameBase;
use Elastic\Apm\Impl\Util\StackTraceUtil;
use Elastic\Apm\Impl\Util\RangeUtil;
use Elastic\Apm\Impl\Util\TextUtil;
use ElasticApmTests\Util\IterableUtilForTests;
use ElasticApmTests\Util\SpanDto;
use ElasticApmTests\Util\TestCaseBase;

class StackTraceUtilTest extends TestCaseBase
{
    private const EXPECTED_CAPTURED_CLASSIC_STACK_TRACE_TOP_KEY = 'EXPECTED_CAPTURED_CLASSIC_STACK_TRACE_TOP';
    private const EXPECTED_CONVERTED_CLASSIC_STACK_TRACE_TOP_KEY = 'EXPECTED_CONVERTED_CLASSIC_STACK_TRACE_TOP';
    private const ACTUAL_CAPTURED_PHP_STACK_TRACE_KEY = 'ACTUAL_CAPTURED_PHP_STACK_TRACE';
    private const ACTUAL_CAPTURED_CLASSIC_STACK_TRACE_KEY = 'ACTUAL_CAPTURED_CLASSIC_STACK_TRACE';

    private const MAX_FUNC_DEPTH_KEY = 'MAX_FUNC_DEPTH';
    private const DEBUG_BACKTRACE_OPTIONS_KEY = 'DEBUG_BACKTRACE_OPTIONS';
    private const STACK_TRACE_SIZE_LIMIT_KEY = 'STACK_TRACE_SIZE_LIMIT';

    private const VERY_LARGE_STACK_TRACE_SIZE_LIMIT = 1000;

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
    public function testConvertClassAndMethodToFunctionName(
        ?string $classicName,
        ?bool $isStaticMethod,
        ?string $methodName,
        ?string $expectedFuncName
    ): void {
        $actualFuncName
            = StackTraceUtil::convertClassAndMethodToFunctionName($classicName, $isStaticMethod, $methodName);
        $ctx = LoggableToString::convert(
            [
                'expectedFuncName' => $expectedFuncName,
                'actualFuncName'   => $actualFuncName,
                'classicName'      => $classicName,
                'isStaticMethod'   => $isStaticMethod,
                'methodName'       => $methodName,
            ]
        );
        self::assertSame($expectedFuncName, $actualFuncName, $ctx);
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
     * @return ClassicFormatStackTraceFrame
     */
    private static function buildClassicFormatFrame(array $debugBacktraceFormatFrame): ClassicFormatStackTraceFrame
    {
        $newFrame = new ClassicFormatStackTraceFrame();
        $newFrame->copyDataFromFromDebugBacktraceFrame($debugBacktraceFormatFrame);
        return $newFrame;
    }

    public function testSimpleConvertPhpVsClassicFormats(): void
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
        $actualPhpFormatConvertedToClassic
            = StackTraceUtil::convertPhpToClassicFormatOmitTopFrame($phpFormatStackTrace);
        self::assertStackTraceMatchesExpected($expectedPhpFormatConvertedToClassic, $actualPhpFormatConvertedToClassic);

        $expectedConvertedBackToPhpFormat = [
            self::buildPhpFormatFrame(
                [
                    StackTraceUtil::FILE_KEY => 'Helper.php',
                    StackTraceUtil::LINE_KEY => 333,
                ]
            ),
            self::buildPhpFormatFrame(
                [
                    StackTraceUtil::FILE_KEY     => 'main.php',
                    StackTraceUtil::LINE_KEY     => 22,
                    StackTraceUtil::CLASS_KEY    => 'Helper',
                    StackTraceUtil::FUNCTION_KEY => 'someHelperMethod',
                    'any_other_key'              => 'any_other_key value for Helper::someHelperMethod',
                ]
            ),
            self::buildPhpFormatFrame(
                [
                    StackTraceUtil::FILE_KEY     => 'bootstrap.php',
                    StackTraceUtil::LINE_KEY     => 1,
                    StackTraceUtil::FUNCTION_KEY => 'main',
                    'any_other_key'              => 'any_other_key value for main',
                ]
            ),
        ];
        $actualConvertedBackToPhpFormat
            = StackTraceUtil::convertClassicToPhpFormat($actualPhpFormatConvertedToClassic);
        self::assertStackTraceMatchesExpected($expectedConvertedBackToPhpFormat, $actualConvertedBackToPhpFormat);
    }

    private static function buildApmFormatFrame(string $file, int $line, ?string $func): StackTraceFrame
    {
        $newFrame = new StackTraceFrame($file, $line);
        $newFrame->function = $func;
        return $newFrame;
    }

    /**
     * @param StackTraceFrame[]    $expectedStackTrace
     * @param StackTraceFrame[]    $actualStackTrace
     * @param array<string, mixed> $ctxOuter
     *
     * @return void
     */
    public static function assertEqualApmStackTraces(
        array $expectedStackTrace,
        array $actualStackTrace,
        array $ctxOuter = []
    ): void {
        SpanDto::assertStackTraceMatches(
            $expectedStackTrace,
            false /* <- allowExpectedStackTraceToBePrefix */,
            $actualStackTrace,
            $ctxOuter
        );
    }

    public function testSimpleConvertClassicToApmFormat(): void
    {
        $classicFormatStackTrace = [
            self::buildClassicFormatFrame(
                [
                    StackTraceUtil::FILE_KEY     => 'Helper2.php',
                    StackTraceUtil::LINE_KEY     => 4444,
                    StackTraceUtil::CLASS_KEY    => 'Helper2',
                    StackTraceUtil::FUNCTION_KEY => 'someStaticHelperMethod',
                    StackTraceUtil::TYPE_KEY     => '::',
                ]
            ),
            self::buildClassicFormatFrame(
                [
                    StackTraceUtil::FILE_KEY     => 'Helper.php',
                    StackTraceUtil::LINE_KEY     => 333,
                    StackTraceUtil::CLASS_KEY    => 'Helper',
                    StackTraceUtil::FUNCTION_KEY => 'someHelperMethod',
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
            self::buildApmFormatFrame('Helper2.php', 4444, 'Helper2::someStaticHelperMethod'),
            self::buildApmFormatFrame('Helper.php', 333, 'Helper->someHelperMethod'),
            self::buildApmFormatFrame('main.php', 22, 'main'),
            self::buildApmFormatFrame('bootstrap.php', 1, null),
        ];

        $actualApmFormatStackTrace = StackTraceUtil::convertClassicToApmFormat($classicFormatStackTrace);
        self::assertEqualApmStackTraces($expectedApmFormatStackTrace, $actualApmFormatStackTrace);
    }

    /**
     * @param ?int $debugBacktraceOptions
     *
     * @return bool
     */
    private static function expectedToCaptureThisObject(?int $debugBacktraceOptions): bool
    {
        return ($debugBacktraceOptions === null)
               || (($debugBacktraceOptions & DEBUG_BACKTRACE_PROVIDE_OBJECT) !== 0);
    }

    /**
     * @param ?int $debugBacktraceOptions
     *
     * @return bool
     */
    private static function expectedToCaptureArgs(?int $debugBacktraceOptions): bool
    {
        return ($debugBacktraceOptions === null)
               || (($debugBacktraceOptions & DEBUG_BACKTRACE_IGNORE_ARGS) === 0);
    }

    /**
     * @return iterable<array{?int, ?int, bool}>
     */
    public function dataProviderForTestSimpleCapture(): iterable
    {
        $debugBacktraceOptionsVariants = [null, DEBUG_BACKTRACE_IGNORE_ARGS];

        foreach ($debugBacktraceOptionsVariants as $debugBacktraceOptions) {
            foreach ([null, 0, 1, 2, 3, self::VERY_LARGE_STACK_TRACE_SIZE_LIMIT] as $stackTraceSizeLimit) {
                foreach (IterableUtilForTests::ALL_BOOL_VALUES as $withOffset) {
                    yield [$debugBacktraceOptions, $stackTraceSizeLimit, $withOffset];
                }
            }
        }
    }

    private static function assertArgsPresentAsExpected(
        ?int $debugBacktraceOptions,
        StackTraceFrameBase $frame,
        string $message = ''
    ): void {
        if (self::expectedToCaptureArgs($debugBacktraceOptions)) {
            self::assertNotNull($frame->args, $message);
        } else {
            self::assertNull($frame->args, $message);
        }
    }

    /**
     * @dataProvider dataProviderForTestSimpleCapture
     *
     * @param ?int $debugBacktraceOptions
     * @param ?int $stackTraceSizeLimit
     * @param bool $withOffset
     */
    public function testSimpleCapturePhpFormat(
        ?int $debugBacktraceOptions,
        ?int $stackTraceSizeLimit,
        bool $withOffset
    ): void {
        /** @var null|PhpFormatStackTraceFrame[] $actualStackTrace */
        $actualStackTrace = null;
        /** @var array<string, mixed>[] $actualDebugBackTrace */
        $actualDebugBackTrace = null;
        /** @var ?int $expectedLine */
        $expectedLine = null;
        $callCaptureInPhpFormat = function () use (
            &$actualStackTrace,
            &$actualDebugBackTrace,
            &$expectedLine,
            $debugBacktraceOptions,
            $stackTraceSizeLimit,
            $withOffset
        ): void {
            $actualDebugBackTrace = debug_backtrace(
                $debugBacktraceOptions ?? DEBUG_BACKTRACE_PROVIDE_OBJECT,
                $stackTraceSizeLimit ?? 0
            );
            if ($withOffset) {
                $actualStackTrace = self::captureInPhpFormat($debugBacktraceOptions, $stackTraceSizeLimit);
                $expectedLine = __LINE__ - 1;
            } else {
                $args = [
                    self::noopLoggerFactory(),
                    /* offset */
                    0,
                    $debugBacktraceOptions ?? DEBUG_BACKTRACE_PROVIDE_OBJECT,
                    $stackTraceSizeLimit ?? 0,
                ];
                $actualStackTrace = StackTraceUtil::captureInPhpFormat(...$args);
                $expectedLine = __LINE__ - 1;
            }
        };
        $callCaptureInPhpFormat();
        $expectedLineNextFrame = __LINE__ - 1;

        $expectedArgs = func_get_args();
        $dbgCtxTop = [
            'debugBacktraceOptions'       => $debugBacktraceOptions,
            'expectedToCaptureThisObject' => self::expectedToCaptureThisObject($debugBacktraceOptions),
            'expectedToCaptureArgs'       => self::expectedToCaptureArgs($debugBacktraceOptions),
            'stackTraceSizeLimit'         => $stackTraceSizeLimit,
            'actualStackTrace'            => $actualStackTrace,
            'expectedLine'                => $expectedLine,
            'expectedLineNextFrame'       => $expectedLineNextFrame,
            'expectedArgs'                => $expectedArgs,
            'actualDebugBackTrace'        => $actualDebugBackTrace,
        ];
        $dbgCtxTopStr = LoggableToString::convert($dbgCtxTop);

        self::assertNotNull($actualStackTrace, $dbgCtxTopStr);
        self::assertGreaterThanOrEqual(1, count($actualStackTrace), $dbgCtxTopStr);
        if ($stackTraceSizeLimit !== null && $stackTraceSizeLimit !== 0) {
            self::assertLessThanOrEqual($stackTraceSizeLimit, count($actualStackTrace), $dbgCtxTopStr);
            if ($stackTraceSizeLimit < 3) {
                self::assertCount($stackTraceSizeLimit, $actualStackTrace, $dbgCtxTopStr);
            }
        }

        for ($i = 0; $i < count($actualStackTrace); ++$i) {
            $frame = $actualStackTrace[$i];
            $dbgCtxPerIt = array_merge(['frame' => $frame, 'i' => $i], $dbgCtxTop);
            $dbgCtxPerItStr = LoggableToString::convert($dbgCtxPerIt);
            if ($i !== 0) {
                self::assertArgsPresentAsExpected($debugBacktraceOptions, $frame, $dbgCtxPerItStr);
            }
            switch ($i) {
                case 0:
                    self::assertSame(__FILE__, $frame->file, $dbgCtxPerItStr);
                    self::assertSame($expectedLine, $frame->line, $dbgCtxPerItStr);
                    self::assertNull($frame->function, $dbgCtxPerItStr);
                    self::assertNull($frame->class, $dbgCtxPerItStr);
                    self::assertNull($frame->isStaticMethod, $dbgCtxPerItStr);
                    self::assertNull($frame->thisObj, $dbgCtxPerItStr);
                    self::assertNull($frame->args, $dbgCtxPerItStr);
                    break;
                case 1:
                    self::assertSame(__FILE__, $frame->file, $dbgCtxPerItStr);
                    self::assertSame($expectedLineNextFrame, $frame->line, $dbgCtxPerItStr);
                    self::assertNotNull($frame->function, $dbgCtxPerItStr);
                    self::assertTrue(TextUtil::contains($frame->function, 'closure'), $dbgCtxPerItStr);
                    self::assertSame(__CLASS__, $frame->class, $dbgCtxPerItStr);
                    self::assertFalse($frame->isStaticMethod, $dbgCtxPerItStr);
                    if (self::expectedToCaptureArgs($debugBacktraceOptions)) {
                        self::assertNotNull($frame->args, $dbgCtxPerItStr);
                        self::assertCount(0, $frame->args, $dbgCtxPerItStr);
                    }
                    break;
                case 2:
                    self::assertNotEquals(__FILE__, $frame->file, $dbgCtxPerItStr);
                    self::assertNotNull($frame->line, $dbgCtxPerItStr);
                    self::assertSame(__FUNCTION__, $frame->function, $dbgCtxPerItStr);
                    self::assertSame(__CLASS__, $frame->class, $dbgCtxPerItStr);
                    if (self::expectedToCaptureArgs($debugBacktraceOptions)) {
                        self::assertNotNull($frame->args, $dbgCtxPerItStr);
                        self::assertEqualAsSets($expectedArgs, $frame->args, $dbgCtxPerItStr);
                    }
                    self::assertNotNull($frame->isStaticMethod, $dbgCtxPerItStr);
                    break;
            }
        }
    }

    /**
     * @dataProvider dataProviderForTestSimpleCapture
     *
     * @param ?int $debugBacktraceOptions
     * @param ?int $stackTraceSizeLimit
     * @param bool $withOffset
     */
    public function testSimpleCaptureClassicFormat(
        ?int $debugBacktraceOptions,
        ?int $stackTraceSizeLimit,
        bool $withOffset
    ): void {
        if ($withOffset) {
            $frames = self::captureInClassicFormat($debugBacktraceOptions, $stackTraceSizeLimit);
            $expectedLine = __LINE__ - 1;
        } else {
            $args = [
                self::noopLoggerFactory(),
                /* offset */ 0,
                $debugBacktraceOptions ?? DEBUG_BACKTRACE_PROVIDE_OBJECT,
                $stackTraceSizeLimit ?? 0
            ];
            $frames = StackTraceUtil::captureInClassicFormat(...$args);
            $expectedLine = __LINE__ - 1;
        }
        $expectedArgs = func_get_args();
        $ctx = LoggableToString::convert(
            [
                'debugBacktraceOptions'       => $debugBacktraceOptions,
                'expectedToCaptureThisObject' => self::expectedToCaptureThisObject($debugBacktraceOptions),
                'expectedToCaptureArgs'       => self::expectedToCaptureArgs($debugBacktraceOptions),
                'stackTraceSizeLimit'         => $stackTraceSizeLimit,
                'frames'                      => $frames,
                'expectedLine'                => $expectedLine,
                'expectedArgs'                => $expectedArgs,
            ]
        );

        self::assertGreaterThanOrEqual(1, count($frames), $ctx);
        if ($stackTraceSizeLimit !== null && $stackTraceSizeLimit !== 0) {
            self::assertLessThanOrEqual($stackTraceSizeLimit, count($frames), $ctx);
            if ($stackTraceSizeLimit < 3) {
                self::assertCount($stackTraceSizeLimit, $frames, $ctx);
            }
        }
        $topFrame = $frames[0];
        self::assertSame(__FILE__, $topFrame->file, $ctx);
        self::assertSame($expectedLine, $topFrame->line, $ctx);
        self::assertSame(__FUNCTION__, $topFrame->function, $ctx);
        self::assertSame(__CLASS__, $topFrame->class, $ctx);
        self::assertFalse($topFrame->isStaticMethod, $ctx);

        if (self::expectedToCaptureThisObject($debugBacktraceOptions)) {
            self::assertNotNull($topFrame->thisObj, $ctx);
            self::assertSame($this, $topFrame->thisObj, $ctx);
        } else {
            self::assertNull($topFrame->thisObj, $ctx);
        }

        if (self::expectedToCaptureArgs($debugBacktraceOptions)) {
            self::assertNotNull($topFrame->args, $ctx);
            self::assertEqualAsSets($expectedArgs, $topFrame->args, $ctx);
        } else {
            self::assertNull($topFrame->args, $ctx);
        }
    }

    /**
     * @param array<string, mixed> $testArgs
     *
     * @return int
     */
    private static function getMaxDepth(array $testArgs): int
    {
        $result = $testArgs[self::MAX_FUNC_DEPTH_KEY];
        self::assertIsInt($result);
        return $result;
    }

    /**
     * @param array<string, mixed> $testArgs
     *
     * @return ?int
     */
    private static function getDebugBacktraceOptions(array $testArgs): ?int
    {
        $result = $testArgs[self::DEBUG_BACKTRACE_OPTIONS_KEY];
        if ($result !== null) {
            self::assertIsInt($result);
        }
        return $result;
    }

    /**
     * @param array<string, mixed> $testArgs
     *
     * @return ?int
     */
    private static function getStackTraceSizeLimit(array $testArgs): ?int
    {
        $result = $testArgs[self::STACK_TRACE_SIZE_LIMIT_KEY];
        if ($result !== null) {
            self::assertIsInt($result);
        }
        return $result;
    }

    /**
     * @return PhpFormatStackTraceFrame[]
     */
    private static function captureInPhpFormat(?int $debugBacktraceOptions, ?int $stackTraceSizeLimit): array
    {
        if ($debugBacktraceOptions === null) {
            if ($stackTraceSizeLimit === null) {
                $result = StackTraceUtil::captureInPhpFormat(
                    self::noopLoggerFactory(),
                    1 /* <- offset */
                );
            } else {
                $result = StackTraceUtil::captureInPhpFormat(
                    self::noopLoggerFactory(),
                    1 /* <- offset */,
                    DEBUG_BACKTRACE_PROVIDE_OBJECT,
                    $stackTraceSizeLimit
                );
            }
        } else {
            if ($stackTraceSizeLimit === null) {
                $result = StackTraceUtil::captureInPhpFormat(
                    self::noopLoggerFactory(),
                    1 /* <- offset */,
                    $debugBacktraceOptions
                );
            } else {
                $result = StackTraceUtil::captureInPhpFormat(
                    self::noopLoggerFactory(),
                    1 /* <- offset */,
                    $debugBacktraceOptions,
                    $stackTraceSizeLimit
                );
            }
        }

        return $result;
    }

    /**
     * @return ClassicFormatStackTraceFrame[]
     */
    private static function captureInClassicFormat(?int $debugBacktraceOptions, ?int $stackTraceSizeLimit): array
    {
        if ($debugBacktraceOptions === null) {
            if ($stackTraceSizeLimit === null) {
                $result = StackTraceUtil::captureInClassicFormat(
                    self::noopLoggerFactory(),
                    1 /* <- offset */
                );
            } else {
                $result = StackTraceUtil::captureInClassicFormat(
                    self::noopLoggerFactory(),
                    1 /* <- offset */,
                    DEBUG_BACKTRACE_PROVIDE_OBJECT,
                    $stackTraceSizeLimit
                );
            }
        } else {
            if ($stackTraceSizeLimit === null) {
                $result = StackTraceUtil::captureInClassicFormat(
                    self::noopLoggerFactory(),
                    1 /* <- offset */,
                    $debugBacktraceOptions
                );
            } else {
                $result = StackTraceUtil::captureInClassicFormat(
                    self::noopLoggerFactory(),
                    1 /* <- offset */,
                    $debugBacktraceOptions,
                    $stackTraceSizeLimit
                );
            }
        }
        return $result;
    }

    /**
     * @param ?self                $thisObj
     * @param int                  $funcDepth
     * @param array<string, mixed> $testArgs
     * @param string               $funcNameCalledFrom
     * @param bool                 $isFuncCalledFromStatic
     * @param int                  $lineNumberWithCallToFuncImpl
     *
     * @return array<string, mixed>
     */
    private static function funcImpl(
        ?self $thisObj,
        int $funcDepth,
        array $testArgs,
        string $funcNameCalledFrom,
        bool $isFuncCalledFromStatic,
        int $lineNumberWithCallToFuncImpl
    ): array {
        $maxDepth = self::getMaxDepth($testArgs);
        $debugBacktraceOptions = self::getDebugBacktraceOptions($testArgs);
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
                    self::fail(LoggableToString::convert(['funcDepth' => $funcDepth, 'maxDepth' => $maxDepth]));
            }
        } else {
            $result = [];
            $stackTraceSizeLimit = self::getStackTraceSizeLimit($testArgs);
            $result[self::ACTUAL_CAPTURED_PHP_STACK_TRACE_KEY]
                = self::captureInPhpFormat($debugBacktraceOptions, $stackTraceSizeLimit);
            $expectedConvertedLine = __LINE__ - 1;
            $result[self::ACTUAL_CAPTURED_CLASSIC_STACK_TRACE_KEY]
                = self::captureInClassicFormat($debugBacktraceOptions, $stackTraceSizeLimit);
            $expectedCapturedLine = __LINE__ - 1;
            $result[self::EXPECTED_CONVERTED_CLASSIC_STACK_TRACE_TOP_KEY] = [];
            $result[self::EXPECTED_CAPTURED_CLASSIC_STACK_TRACE_TOP_KEY] = [];
        }

        $currentFrame = new ClassicFormatStackTraceFrame();
        $currentFrame->file = __FILE__;
        $currentFrame->line = $expectedConvertedLine;
        $currentFrame->function = __FUNCTION__;
        $currentFrame->class = __CLASS__;
        $currentFrame->isStaticMethod = true;
        if (self::expectedToCaptureArgs($debugBacktraceOptions)) {
            $currentFrame->args = func_get_args();
        }

        $callingFuncFrame = new ClassicFormatStackTraceFrame();
        $callingFuncFrame->file = __FILE__;
        $callingFuncFrame->line = $lineNumberWithCallToFuncImpl;
        $callingFuncFrame->function = $funcNameCalledFrom;
        $callingFuncFrame->class = __CLASS__;
        $callingFuncFrame->isStaticMethod = $isFuncCalledFromStatic;

        /** @var ClassicFormatStackTraceFrame[] $expectedConvertedClassicStackTraceTop */
        $expectedConvertedClassicStackTraceTop = &$result[self::EXPECTED_CONVERTED_CLASSIC_STACK_TRACE_TOP_KEY];
        $expectedConvertedClassicStackTraceTop[] = $currentFrame;
        $expectedConvertedClassicStackTraceTop[] = $callingFuncFrame;
        unset($expectedConvertedClassicStackTraceTop);

        /** @var ClassicFormatStackTraceFrame[] $expectedCapturedClassicStackTraceTop */
        $expectedCapturedClassicStackTraceTop = &$result[self::EXPECTED_CAPTURED_CLASSIC_STACK_TRACE_TOP_KEY];
        $currentFrameAdapted = clone $currentFrame;
        $currentFrameAdapted->line = $expectedCapturedLine;
        $expectedCapturedClassicStackTraceTop[] = $currentFrameAdapted;
        $expectedCapturedClassicStackTraceTop[] = $callingFuncFrame;
        unset($expectedCapturedClassicStackTraceTop);
        return $result;
    }

    /**
     * @param array<string, mixed> $testArgs
     *
     * @return array<string, mixed>
     */
    private function funcA(array $testArgs): array
    {
        return self::funcImpl($this, /* funcDepth */ 1, $testArgs, __FUNCTION__, /* isStatic */ false, __LINE__);
    }

    /**
     * @param string               $calledFromFunc
     * @param int                  $funcDepth
     * @param array<string, mixed> $testArgs
     *
     * @return array<string, mixed>
     *
     * @noinspection PhpSameParameterValueInspection
     */
    private function funcB(string $calledFromFunc, int $funcDepth, array $testArgs): array
    {
        self::assertSame('funcA', $calledFromFunc);
        return self::funcImpl($this, $funcDepth, $testArgs, __FUNCTION__, /* isStatic */ false, __LINE__);
    }

    /**
     * @param string               $calledFromFunc
     * @param int                  $funcDepth
     * @param array<string, mixed> $testArgs
     *
     * @return array<string, mixed>
     *
     * @noinspection PhpSameParameterValueInspection
     */
    private static function funcC(string $calledFromFunc, int $funcDepth, array $testArgs): array
    {
        self::assertSame('funcB', $calledFromFunc);
        return self::funcImpl(/* thisObj */ null, $funcDepth, $testArgs, __FUNCTION__, /* isStatic */ true, __LINE__);
    }

    /**
     * @param PhpFormatStackTraceFrame[]     $phpFormatStackTrace
     * @param ClassicFormatStackTraceFrame[] $classicFormatStackTrace
     *
     * @return void
     */
    public static function assertPhpFormatMatchesClassic(
        array $phpFormatStackTrace,
        array $classicFormatStackTrace
    ): void {
        self::assertSame(
            count($phpFormatStackTrace),
            count($classicFormatStackTrace),
            LoggableToString::convert(
                [
                    'phpFormatStackTrace' => $phpFormatStackTrace,
                    'classicFormatStackTrace' => $classicFormatStackTrace
                ]
            )
        );

        foreach (RangeUtil::generateUpTo(count($classicFormatStackTrace)) as $frameIndex) {
            $classicCurrentFrame = $classicFormatStackTrace[$frameIndex];
            $phpFrameWithLocationData = $phpFormatStackTrace[$frameIndex];
            $phpFrameWithNonLocationData
                = $frameIndex + 1 < count($phpFormatStackTrace) ? $phpFormatStackTrace[$frameIndex + 1] : null;
            foreach (get_object_vars($classicCurrentFrame) as $propName => $classicVal) {
                if ($classicVal === null) {
                    continue;
                }
                $ctx = LoggableToString::convert(
                    [
                        'propName'                    => $propName,
                        'classicVal'                  => $classicVal,
                        'phpFrameWithLocationData'    => $phpFrameWithLocationData,
                        'phpFrameWithNonLocationData' => $phpFrameWithNonLocationData,
                        'frameIndex'                  => $frameIndex,
                        'phpFormatStackTrace'         => $phpFormatStackTrace,
                        'classicFormatStackTrace'     => $classicFormatStackTrace,
                    ]
                );
                if (StackTraceFrameBase::isLocationProperty($propName)) {
                    self::assertSame($classicVal, $phpFrameWithLocationData->{$propName}, $ctx);
                } else {
                    if ($phpFrameWithNonLocationData !== null) {
                        self::assertSame($classicVal, $phpFrameWithNonLocationData->{$propName}, $ctx);
                    }
                }
            }
        }
    }

    /**
     * @template TStackFrameFormat of StackTraceFrameBase
     *
     * @param TStackFrameFormat[] $expected
     * @param TStackFrameFormat[] $actual
     *
     * @return void
     */
    private static function assertStackTraceMatchesExpected(array $expected, array $actual): void
    {
        self::assertSame(
            count($expected),
            count($actual),
            LoggableToString::convert(['expected' => $expected, 'actual' => $actual])
        );
        foreach (RangeUtil::generateUpTo(count($expected)) as $frameIndex) {
            $expectedStackFrame = $expected[$frameIndex];
            $actualStackFrame = $actual[$frameIndex];
            foreach (get_object_vars($expectedStackFrame) as $expectedKey => $expectedVal) {
                if ($expectedVal === null) {
                    continue;
                }
                self::assertSame(
                    $expectedVal,
                    $actualStackFrame->{$expectedKey},
                    LoggableToString::convert(
                        [
                            'expectedKey'        => $expectedKey,
                            'expectedVal'        => $expectedVal,
                            'actualStackFrame'   => $actualStackFrame,
                            'expectedStackFrame' => $expectedStackFrame,
                            'frameIndex'         => $frameIndex,
                            'expected'           => $expected,
                            'actual'             => $actual,
                        ]
                    )
                );
            }
        }
    }

    /**
     * @template TStackFrameFormat of StackTraceFrameBase
     *
     * @param string               $dbgStackTraceDesc
     * @param TStackFrameFormat[]  $stackTrace
     * @param bool                 $isActual
     * @param array<string, mixed> $testArgs
     */
    private static function assertValidStackTrace(
        string $dbgStackTraceDesc,
        array $stackTrace,
        bool $isActual,
        array $testArgs
    ): void {
        $debugBacktraceOptions = self::getDebugBacktraceOptions($testArgs);
        foreach (RangeUtil::generateUpTo(count($stackTrace)) as $i) {
            $frame = $stackTrace[$i];
            $ctx = LoggableToString::convert(
                [
                    'i'                 => $i,
                    'frame'             => $frame,
                    'dbgStackTraceDesc' => $dbgStackTraceDesc,
                    'stackTrace'        => $stackTrace,
                    'testArgs'          => $testArgs,
                ]
            );
            $maybeLocationDataOnlyFrame = ($stackTrace[0] instanceof PhpFormatStackTraceFrame)
                ? $i === 0
                : $i === (count($stackTrace) - 1);
            if ($isActual && !$maybeLocationDataOnlyFrame) {
                self::assertArgsPresentAsExpected($debugBacktraceOptions, $frame, $ctx);
            }
        }
    }

    /**
     * @template TStackFrameFormat of StackTraceFrameBase
     *
     * @param string              $testFuncName
     * @param string              $dbgStackTraceDesc
     * @param TStackFrameFormat[] $stackTrace
     * @param int                 $maxDepth
     */
    private static function assertStackTraceTopAsExpected(
        string $testFuncName,
        string $dbgStackTraceDesc,
        array $stackTrace,
        int $maxDepth
    ): void {
        self::assertGreaterThanZero(count($stackTrace));
        $isPhpFormat = $stackTrace[0] instanceof PhpFormatStackTraceFrame;

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
        foreach ($expectedFuncNames as [$index, $funcName, $isStatic, $expectedCalledFromFunc, $expectedFuncDepth]) {
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
            $ctx = LoggableToString::convert(
                [
                    'stackTraceFrame'   => $stackTraceFrame,
                    'dbgStackTraceDesc' => $dbgStackTraceDesc,
                    'stackTrace'        => $stackTrace,
                    'index'             => $index,
                    'maxDepth'          => $maxDepth,
                ]
            );
            self::assertSame($funcName, $stackTraceFrame->function, $ctx);
            self::assertSame($isStatic, $stackTraceFrame->isStaticMethod, $ctx);
            if (($args = $stackTraceFrame->args) !== null) {
                if ($expectedCalledFromFunc !== null) {
                    $calledFromFuncArgNum = 0;
                    self::assertSameValueInArray($calledFromFuncArgNum, $expectedCalledFromFunc, $args, $ctx);
                }
                if ($expectedFuncDepth !== null) {
                    $funcDepthArgNum = 1;
                    self::assertSameValueInArray($funcDepthArgNum, $expectedFuncDepth, $args, $ctx);
                }
            }
        }

        foreach ([0, 2, 4] as $expectedFuncImplIndex) {
            $funcImplClosestToBottom = $funcAIndex - 1;
            if ($isPhpFormat) {
                ++$expectedFuncImplIndex;
                ++$funcImplClosestToBottom;
            }
            if ($expectedFuncImplIndex < count($stackTrace) && $expectedFuncImplIndex <= $funcImplClosestToBottom) {
                self::assertSame(
                    'funcImpl',
                    $stackTrace[$expectedFuncImplIndex]->function,
                    LoggableToString::convert(
                        [
                            'expectedFuncImplIndex'   => $expectedFuncImplIndex,
                            'funcImplClosestToBottom' => $funcImplClosestToBottom,
                            'isPhpFormat'             => $isPhpFormat,
                            'dbgStackTraceDesc'       => $dbgStackTraceDesc,
                            'stackTrace'              => $stackTrace,
                            'maxDepth'                => $maxDepth,
                        ]
                    )
                );
            }
        }
    }

    /**
     * @return iterable<array{array<string, mixed>}>
     */
    public function dataProviderForTestConvertPhpFormatToClassic(): iterable
    {
        $debugBacktraceOptionsVariants = [null, DEBUG_BACKTRACE_IGNORE_ARGS];

        foreach (RangeUtil::generateFromToIncluding(1, 3) as $maxDepth) {
            foreach ($debugBacktraceOptionsVariants as $debugBacktraceOptions) {
                foreach ([null, 1, $maxDepth * 2, self::VERY_LARGE_STACK_TRACE_SIZE_LIMIT, 0] as $stackTraceSizeLimit) {
                    yield [
                        [
                            self::MAX_FUNC_DEPTH_KEY          => $maxDepth,
                            self::DEBUG_BACKTRACE_OPTIONS_KEY => $debugBacktraceOptions,
                            self::STACK_TRACE_SIZE_LIMIT_KEY  => $stackTraceSizeLimit,
                        ],
                    ];
                }
            }
        }
    }

    /**
     * @dataProvider dataProviderForTestConvertPhpFormatToClassic
     *
     * @param array<string, mixed> $testArgs
     */
    public function testPhpVsClassicFormats(array $testArgs): void
    {
        $result = $this->funcA($testArgs);
        $funcACallLineNumber = __LINE__ - 1;

        /** @var PhpFormatStackTraceFrame[] $actualCapturedPhp */
        $actualCapturedPhp = $result[self::ACTUAL_CAPTURED_PHP_STACK_TRACE_KEY];
        /** @var ClassicFormatStackTraceFrame[] $actualCapturedClassic */
        $actualCapturedClassic = $result[self::ACTUAL_CAPTURED_CLASSIC_STACK_TRACE_KEY];

        $debugBacktraceOptions = self::getDebugBacktraceOptions($testArgs);
        $stackTraceSizeLimit = self::getStackTraceSizeLimit($testArgs);
        $isLimitEffective = $stackTraceSizeLimit !== null
                            && $stackTraceSizeLimit !== 0
                            && $stackTraceSizeLimit !== self::VERY_LARGE_STACK_TRACE_SIZE_LIMIT;

        $phpStackTraceToHere = self::captureInPhpFormat($debugBacktraceOptions, $stackTraceSizeLimit);
        $phpStackTraceToHere[0]->line = $funcACallLineNumber;
        $classicStackTraceToHere = self::captureInClassicFormat($debugBacktraceOptions, $stackTraceSizeLimit);
        $classicStackTraceToHere[0]->line = $funcACallLineNumber;

        /** @var PhpFormatStackTraceFrame[] $expectedConvertedClassicTop */
        $expectedConvertedClassicTop = $result[self::EXPECTED_CONVERTED_CLASSIC_STACK_TRACE_TOP_KEY];
        /** @var ClassicFormatStackTraceFrame[] $expectedConvertedClassic */
        $expectedConvertedClassic = array_merge($expectedConvertedClassicTop, $classicStackTraceToHere);
        if ($isLimitEffective) {
            $expectedConvertedClassic = array_slice($expectedConvertedClassic, 0, $stackTraceSizeLimit);
        }

        /** @var ClassicFormatStackTraceFrame[] $expectedCapturedClassicTop */
        $expectedCapturedClassicTop = $result[self::EXPECTED_CAPTURED_CLASSIC_STACK_TRACE_TOP_KEY];
        /** @var ClassicFormatStackTraceFrame[] $expectedCapturedClassic */
        $expectedCapturedClassic = array_merge($expectedCapturedClassicTop, $classicStackTraceToHere);
        if ($isLimitEffective) {
            $expectedCapturedClassic = array_slice($expectedCapturedClassic, 0, $stackTraceSizeLimit);
        }

        $maxDepth = self::getMaxDepth($testArgs);

        $allStackTraces = [
            'actualCapturedPhp'        => [
                $actualCapturedPhp,
                true /* <- isActual */,
            ],
            'actualCapturedClassic'    => [
                $actualCapturedClassic,
                true /* <- isActual */,
            ],
            'expectedCapturedClassic'  => [
                $expectedCapturedClassic,
                false /* <- isActual */,
            ],
            'expectedConvertedClassic' => [
                $expectedConvertedClassic,
                false /* <- isActual */,
            ],
        ];
        foreach ($allStackTraces as $dbgStackTraceDesc => [$stackTrace, $isActual]) {
            $isPhpFormat = $stackTrace[0] instanceof PhpFormatStackTraceFrame;
            $expectedStackTraceCount = $isLimitEffective
                ? $stackTraceSizeLimit
                : (count($isPhpFormat ? $phpStackTraceToHere : $classicStackTraceToHere) + $maxDepth * 2);
            $ctx = LoggableToString::convert(
                [
                    'expectedStackTraceCount' => $expectedStackTraceCount,
                    'classicStackTraceToHere' => $classicStackTraceToHere,
                    'dbgStackTraceDesc'       => $dbgStackTraceDesc,
                    'stackTrace'              => $stackTrace,
                    'maxDepth'                => $maxDepth,
                    'testArgs'                => $testArgs,
                ]
            );
            self::assertCount($expectedStackTraceCount, $stackTrace, $ctx);
            self::assertValidStackTrace($dbgStackTraceDesc, $stackTrace, $isActual, $testArgs);
            self::assertStackTraceTopAsExpected(__FUNCTION__, $dbgStackTraceDesc, $stackTrace, $maxDepth);
        }

        self::assertStackTraceMatchesExpected($expectedCapturedClassic, $actualCapturedClassic);
        $expectedConvertedClassicAdapted = $expectedConvertedClassic;
        if ($isLimitEffective) {
            $bottomFrame
                = $expectedConvertedClassicAdapted[count($expectedConvertedClassicAdapted) - 1];
            $bottomFrame->resetNonLocationProperties();
        }
        self::assertStackTraceMatchesExpected(
            $expectedConvertedClassicAdapted,
            StackTraceUtil::convertPhpToClassicFormatOmitTopFrame($actualCapturedPhp)
        );

        $actualCapturedPhpAdapted = $actualCapturedPhp;
        $actualCapturedPhpAdapted[0] = clone $actualCapturedPhpAdapted[0];
        $actualCapturedPhpAdapted[0]->line = $expectedCapturedClassic[0]->line;
        self::assertPhpFormatMatchesClassic($actualCapturedPhpAdapted, $expectedCapturedClassic);
        self::assertPhpFormatMatchesClassic($actualCapturedPhpAdapted, $actualCapturedClassic);
        self::assertStackTraceMatchesExpected(
            $actualCapturedPhpAdapted,
            StackTraceUtil::convertClassicToPhpFormat($actualCapturedClassic)
        );
    }
}
