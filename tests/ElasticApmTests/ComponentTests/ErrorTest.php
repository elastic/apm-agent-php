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

namespace ElasticApmTests\ComponentTests;

use Elastic\Apm\Impl\ErrorData;
use Elastic\Apm\Impl\Log\LoggableToString;
use Elastic\Apm\Impl\StacktraceFrame;
use Elastic\Apm\Impl\Util\ArrayUtil;
use Elastic\Apm\Impl\Util\ClassNameUtil;
use Elastic\Apm\Impl\Util\PhpErrorUtil;
use ElasticApmTests\ComponentTests\Util\ComponentTestCaseBase;
use ElasticApmTests\ComponentTests\Util\DataFromAgent;
use ElasticApmTests\ComponentTests\Util\HttpConsts;
use ElasticApmTests\ComponentTests\Util\TestProperties;
use ElasticApmTests\Util\DummyExceptionForTests;
use ElasticApmTests\Util\RangeUtilForTests;
use ElasticApmTests\Util\TestArrayUtil;
use Exception;

final class ErrorTest extends ComponentTestCaseBase
{
    private const STACK_TRACE_FILE_NAME = 'STACK_TRACE_FILE_NAME';
    private const STACK_TRACE_FUNCTION = 'STACK_TRACE_FUNCTION';
    private const STACK_TRACE_LINE_NUMBER = 'STACK_TRACE_LINE_NUMBER';

    private function verifyError(DataFromAgent $dataFromAgent): ErrorData
    {
        $tx = $this->verifyTransactionWithoutSpans($dataFromAgent);
        $err = $dataFromAgent->singleError();

        self::assertSame($tx->id, $err->transactionId);
        self::assertSame($tx->id, $err->parentId);
        self::assertSame($tx->traceId, $err->traceId);
        self::assertNotNull($err->transaction);
        self::assertSame($tx->name, $err->transaction->name);
        self::assertSame($tx->type, $err->transaction->type);
        self::assertSame($tx->isSampled, $err->transaction->isSampled);

        return $err;
    }

    /**
     * @param array<string, mixed> $expectedFrame
     * @param string               $expKey
     * @param mixed                $actualValue
     *
     * @return void
     */
    private static function verifyStacktraceFrameProperty(array $expectedFrame, string $expKey, $actualValue): void
    {
        $expectedValue = ArrayUtil::getValueIfKeyExistsElse($expKey, $expectedFrame, null);
        if ($expectedValue !== null) {
            self::assertSame($expectedValue, $actualValue, $expKey);
        }
    }

    /**
     * @param array<string, mixed>[] $expectedStacktraceTop
     * @param ErrorData              $err
     *
     * @return void
     */
    private static function verifyAppCodeStacktraceTop(array $expectedStacktraceTop, ErrorData $err): void
    {
        self::assertNotNull($err->exception);
        $actualStacktrace = $err->exception->stacktrace;
        self::assertNotNull($actualStacktrace);
        self::assertNotEmpty($actualStacktrace);
        self::assertGreaterThanOrEqual(count($expectedStacktraceTop), count($actualStacktrace));

        /** @var StacktraceFrame */
        $bottomFrame = TestArrayUtil::getLastValue($actualStacktrace);
        self::assertSame('ElasticApmTests\\ComponentTests\\Util\\AppCodeHostBase::run()', $bottomFrame->function);

        foreach (RangeUtilForTests::generate(0, count($expectedStacktraceTop)) as $frameIndex) {
            $expectedFrame = $expectedStacktraceTop[$frameIndex];
            $actualFrame = $actualStacktrace[$frameIndex];

            self::verifyStacktraceFrameProperty($expectedFrame, self::STACK_TRACE_FILE_NAME, $actualFrame->filename);
            self::verifyStacktraceFrameProperty($expectedFrame, self::STACK_TRACE_FUNCTION, $actualFrame->function);
            self::verifyStacktraceFrameProperty($expectedFrame, self::STACK_TRACE_LINE_NUMBER, $actualFrame->lineno);
        }
    }

    public static function appCodeForTestPhpErrorUndefinedVariableWrapper(): void
    {
        appCodeForTestPhpErrorUndefinedVariable();
    }

    public function testPhpErrorUndefinedVariable(): void
    {
        $this->sendRequestToInstrumentedAppAndVerifyDataFromAgent(
            (new TestProperties())
                ->withRoutedAppCode([__CLASS__, 'appCodeForTestPhpErrorUndefinedVariableWrapper']),
            function (DataFromAgent $dataFromAgent): void {
                $err = $this->verifyError($dataFromAgent);
                // TODO: Sergey Kleyman: COMMENT
                // self::printMessage(__METHOD__, '$err: ' . LoggableToString::convert($err));

                $appCodeFile = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'appCodeForTestPhpErrorUndefinedVariable.php';
                self::assertNotNull($err->exception);
                // From PHP 7.4.x to PHP 8.0.x attempting to read an undefined variable
                // was converted from notice to warning
                // https://www.php.net/manual/en/migration80.incompatible.php
                $expectedCode = (version_compare(PHP_VERSION, '8.0.0') < 0) ? E_NOTICE : E_WARNING;
                $expectedType = PhpErrorUtil::getTypeName($expectedCode);
                self::assertNotNull($expectedType, '$expectedCode: ' . $expectedCode);
                self::assertSame($expectedType, $err->exception->type);
                self::assertSame($expectedCode, $err->exception->code);
                $expectedMessage
                    = 'Undefined variable'
                      // "Undefined variable ..." message:
                      //    - PHP before 8: includes colon but does not include dollar sign before variable name
                      //    - PHP 8 and later: does not includes colon but does include dollar sign before variable name
                      . (version_compare(PHP_VERSION, '8.0.0') < 0
                        ? ': '
                        : ' $'
                      )
                      . 'undefinedVariable'
                      . ' in ' . $appCodeFile . ':' . APP_CODE_FOR_TEST_PHP_ERROR_UNDEFINED_VARIABLE_ERROR_LINE_NUMBER;
                self::assertSame($expectedMessage, $err->exception->message);
                self::assertNull($err->exception->module);
                $culpritFunction = __NAMESPACE__ . '\\appCodeForTestPhpErrorUndefinedVariableImpl()';
                self::assertSame($culpritFunction, $err->culprit);

                $expectedStacktraceTop = [
                    [
                        self::STACK_TRACE_FILE_NAME   => $appCodeFile,
                        self::STACK_TRACE_FUNCTION    => $culpritFunction,
                        self::STACK_TRACE_LINE_NUMBER =>
                            APP_CODE_FOR_TEST_PHP_ERROR_UNDEFINED_VARIABLE_CALL_TO_IMPL_LINE_NUMBER,
                    ],
                    [
                        self::STACK_TRACE_FILE_NAME => __FILE__,
                        self::STACK_TRACE_FUNCTION  =>
                            __NAMESPACE__ . '\\appCodeForTestPhpErrorUndefinedVariable()',
                    ],
                    [
                        self::STACK_TRACE_FUNCTION =>
                            __CLASS__ . '::appCodeForTestPhpErrorUndefinedVariableWrapper()',
                    ],
                ];
                self::verifyAppCodeStacktraceTop($expectedStacktraceTop, $err);
            }
        );
    }

    public static function appCodeForTestPhpErrorUncaughtExceptionWrapper(): void
    {
        appCodeForTestPhpErrorUncaughtException();
    }

    public function testPhpErrorUncaughtException(): void
    {
        $this->sendRequestToInstrumentedAppAndVerifyDataFromAgent(
            (new TestProperties())
                ->withRoutedAppCode([__CLASS__, 'appCodeForTestPhpErrorUncaughtExceptionWrapper'])
                // It seems it depends on 'display_errors' whether 200 or 500 is returned on PHP error.
                // We ignore HTTP status since it's not the main point of the test case.
                // For more details see https://bugs.php.net/bug.php?id=50921
                ->withExpectedStatusCode(null),
            function (DataFromAgent $dataFromAgent): void {
                $err = $this->verifyError($dataFromAgent);
                // self::printMessage(__METHOD__, '$err: ' . LoggableToString::convert($err));

                $appCodeFile = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'appCodeForTestPhpErrorUncaughtException.php';
                self::assertNotNull($err->exception);
                $defaultCode = (new Exception(""))->getCode();
                self::assertSame($defaultCode, $err->exception->code);
                self::assertSame(Exception::class, $err->exception->type);
                self::assertNotNull($err->exception->message);
                self::assertSame(APP_CODE_FOR_TEST_PHP_ERROR_UNCAUGHT_EXCEPTION_MESSAGE, $err->exception->message);
                self::assertNull($err->exception->module);
                $culpritFunction = __NAMESPACE__ . '\\appCodeForTestPhpErrorUncaughtExceptionImpl()';
                self::assertSame($culpritFunction, $err->culprit);

                $expectedStacktraceTop = [
                    [
                        self::STACK_TRACE_FILE_NAME   => $appCodeFile,
                        self::STACK_TRACE_FUNCTION    =>
                            __NAMESPACE__ . '\\appCodeForTestPhpErrorUncaughtExceptionImpl()',
                        self::STACK_TRACE_LINE_NUMBER =>
                            APP_CODE_FOR_TEST_PHP_ERROR_UNCAUGHT_EXCEPTION_CALL_TO_IMPL_LINE_NUMBER,
                    ],
                    [
                        self::STACK_TRACE_FILE_NAME => __FILE__,
                        self::STACK_TRACE_FUNCTION  =>
                            __NAMESPACE__ . '\\appCodeForTestPhpErrorUncaughtException()',
                    ],
                    [
                        self::STACK_TRACE_FUNCTION =>
                            __CLASS__ . '::appCodeForTestPhpErrorUncaughtExceptionWrapper()',
                    ],
                ];
                self::verifyAppCodeStacktraceTop($expectedStacktraceTop, $err);
            }
        );
    }

    public static function appCodeForTestCaughtExceptionResponded500Wrapper(): void
    {
        appCodeForTestCaughtExceptionResponded500();
    }

    public function testCaughtExceptionResponded500(): void
    {
        $this->sendRequestToInstrumentedAppAndVerifyDataFromAgent(
            (new TestProperties())
                ->withRoutedAppCode([__CLASS__, 'appCodeForTestCaughtExceptionResponded500Wrapper'])
                ->withExpectedStatusCode(HttpConsts::STATUS_INTERNAL_SERVER_ERROR),
            function (DataFromAgent $dataFromAgent): void {
                if (!$this->testEnv->isHttp()) {
                    self::assertEmpty($dataFromAgent->idToError());
                    return;
                }

                $err = $this->verifyError($dataFromAgent);
                // self::printMessage(__METHOD__, '$err: ' . LoggableToString::convert($err));

                $appCodeFile
                    = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'appCodeForTestCaughtExceptionResponded500.php';
                self::assertNotNull($err->exception);
                self::assertSame(APP_CODE_FOR_TEST_CAUGHT_EXCEPTION_RESPONDED_500_CODE, $err->exception->code);

                $exceptionNamespace = '';
                $exceptionClassName = '';
                ClassNameUtil::splitFqClassName(
                    DummyExceptionForTests::class, /* ref */
                    $exceptionNamespace, /* ref */
                    $exceptionClassName
                );
                self::assertSame('ElasticApmTests\\Util', $exceptionNamespace);
                self::assertSame('DummyExceptionForTests', $exceptionClassName);

                self::assertSame($exceptionNamespace, $err->exception->module);
                self::assertSame($exceptionClassName, $err->exception->type);
                self::assertSame(APP_CODE_FOR_TEST_CAUGHT_EXCEPTION_RESPONDED_500_MESSAGE, $err->exception->message);

                $expectedStacktraceTop = [
                    [
                        self::STACK_TRACE_FILE_NAME   => $appCodeFile,
                        self::STACK_TRACE_FUNCTION    =>
                            __NAMESPACE__ . '\\appCodeForTestCaughtExceptionResponded500Impl()',
                        self::STACK_TRACE_LINE_NUMBER =>
                            APP_CODE_FOR_TEST_CAUGHT_EXCEPTION_RESPONDED_500_CALL_TO_IMPL_LINE_NUMBER,
                    ],
                    [
                        self::STACK_TRACE_FILE_NAME => __FILE__,
                        self::STACK_TRACE_FUNCTION  =>
                            __NAMESPACE__ . '\\appCodeForTestCaughtExceptionResponded500()',
                    ],
                    [
                        self::STACK_TRACE_FUNCTION =>
                            __CLASS__ . '::appCodeForTestCaughtExceptionResponded500Wrapper()',
                    ],
                ];
                self::verifyAppCodeStacktraceTop($expectedStacktraceTop, $err);
            }
        );
    }
}
