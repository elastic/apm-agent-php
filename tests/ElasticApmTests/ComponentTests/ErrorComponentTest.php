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

use Elastic\Apm\ElasticApm;
use Elastic\Apm\Impl\Config\OptionNames;
use Elastic\Apm\Impl\Log\LoggableToString;
use Elastic\Apm\Impl\StackTraceFrame;
use Elastic\Apm\Impl\Util\ArrayUtil;
use Elastic\Apm\Impl\Util\ClassNameUtil;
use Elastic\Apm\Impl\Util\PhpErrorUtil;
use Elastic\Apm\Impl\Util\RangeUtil;
use ElasticApmTests\ComponentTests\Util\AppCodeHostHandle;
use ElasticApmTests\ComponentTests\Util\AppCodeHostParams;
use ElasticApmTests\ComponentTests\Util\AppCodeRequestParams;
use ElasticApmTests\ComponentTests\Util\AppCodeTarget;
use ElasticApmTests\ComponentTests\Util\ComponentTestCaseBase;
use ElasticApmTests\ComponentTests\Util\ExpectedEventCounts;
use ElasticApmTests\ComponentTests\Util\HttpAppCodeRequestParams;
use ElasticApmTests\ComponentTests\Util\HttpConstantsForTests;
use ElasticApmTests\ComponentTests\Util\TestCaseHandle;
use ElasticApmTests\Util\ArrayUtilForTests;
use ElasticApmTests\Util\DataFromAgent;
use ElasticApmTests\Util\DataProviderForTestBuilder;
use ElasticApmTests\Util\DummyExceptionForTests;
use ElasticApmTests\Util\ErrorDto;
use ElasticApmTests\Util\FileUtilForTests;
use Exception;

/**
 * @group smoke
 * @group does_not_require_external_services
 */
final class ErrorComponentTest extends ComponentTestCaseBase
{
    private const STACK_TRACE_FILE_NAME = 'STACK_TRACE_FILE_NAME';
    private const STACK_TRACE_FUNCTION = 'STACK_TRACE_FUNCTION';
    private const STACK_TRACE_LINE_NUMBER = 'STACK_TRACE_LINE_NUMBER';

    private const INCLUDE_IN_ERROR_REPORTING = 'INCLUDE_IN_ERROR_REPORTING';

    private function verifyError(DataFromAgent $dataFromAgent): ErrorDto
    {
        $tx = $this->verifyOneTransactionNoSpans($dataFromAgent);
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
     * @param ErrorDto               $err
     *
     * @return void
     */
    private static function verifyAppCodeStacktraceTop(array $expectedStacktraceTop, ErrorDto $err): void
    {
        self::assertNotNull($err->exception);
        $actualStacktrace = $err->exception->stacktrace;
        self::assertNotNull($actualStacktrace);
        self::assertNotEmpty($actualStacktrace);
        self::assertGreaterThanOrEqual(count($expectedStacktraceTop), count($actualStacktrace));

        /** @var StackTraceFrame $bottomFrame */
        $bottomFrame = ArrayUtilForTests::getLastValue($actualStacktrace);
        self::assertSame('ElasticApmTests\\ComponentTests\\Util\\AppCodeHostBase::run()', $bottomFrame->function);

        foreach (RangeUtil::generate(0, count($expectedStacktraceTop)) as $frameIndex) {
            $expectedFrame = $expectedStacktraceTop[$frameIndex];
            $actualFrame = $actualStacktrace[$frameIndex];

            self::verifyStacktraceFrameProperty($expectedFrame, self::STACK_TRACE_FILE_NAME, $actualFrame->filename);
            self::verifyStacktraceFrameProperty($expectedFrame, self::STACK_TRACE_FUNCTION, $actualFrame->function);
            self::verifyStacktraceFrameProperty($expectedFrame, self::STACK_TRACE_LINE_NUMBER, $actualFrame->lineno);
        }
    }

    private static function undefinedVariablePhpErrorCode(): int
    {
        // From PHP 7.4.x to PHP 8.0.x attempting to read an undefined variable
        // was converted from notice to warning
        // https://www.php.net/manual/en/migration80.incompatible.php
        return (version_compare(PHP_VERSION, '8.0.0') < 0) ? E_NOTICE : E_WARNING;
    }

    private static function buildExceptionForSubstituteError(): DummyExceptionForTests
    {
        return new DummyExceptionForTests('Exception for substitute error', /* code: */ 123);
    }

    private static function verifySubstituteError(ErrorDto $err): void
    {
        self::assertNotNull($err->exception);
        self::assertSame('ElasticApmTests\\Util', $err->exception->module);
        self::assertSame('DummyExceptionForTests', $err->exception->type);
        self::assertSame('Exception for substitute error', $err->exception->message);
        self::assertSame(123, $err->exception->code);
    }

    /**
     * @param array<string, mixed> $appCodeArgs
     */
    public static function appCodeForTestPhpErrorUndefinedVariableWrapper(array $appCodeArgs): void
    {
        /** @var bool $includeInErrorReporting */
        $includeInErrorReporting = self::getMandatoryAppCodeArg($appCodeArgs, self::INCLUDE_IN_ERROR_REPORTING);

        $logger = self::getLoggerStatic(__NAMESPACE__, __CLASS__, __FILE__);
        ($loggerProxy = $logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Before changing error_reporting()',
            [
                '$includeInErrorReporting'                                 => $includeInErrorReporting,
                'error_reporting()'                                        => error_reporting(),
                'undefinedVariablePhpErrorCode'                            => self::undefinedVariablePhpErrorCode(),
                'error_reporting() includes undefinedVariablePhpErrorCode' =>
                    ((error_reporting() & self::undefinedVariablePhpErrorCode()) !== 0),
            ]
        );

        if ($includeInErrorReporting) {
            error_reporting(error_reporting() | self::undefinedVariablePhpErrorCode());
        } else {
            error_reporting(error_reporting() & ~self::undefinedVariablePhpErrorCode());
        }

        ($loggerProxy = $logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'After changing error_reporting()',
            [
                'error_reporting()'                                        => error_reporting(),
                'error_reporting() includes undefinedVariablePhpErrorCode' =>
                    ((error_reporting() & self::undefinedVariablePhpErrorCode()) !== 0),
            ]
        );

        appCodeForTestPhpErrorUndefinedVariable();

        if (!$includeInErrorReporting) {
            ElasticApm::createErrorFromThrowable(self::buildExceptionForSubstituteError());
        }
    }

    /**
     * @return iterable<array{bool, bool}>
     */
    public function dataProviderForTestPhpErrorUndefinedVariable(): iterable
    {
        /** @var iterable<array{bool, bool}> $result */
        $result = (new DataProviderForTestBuilder())
            ->addBoolDimensionAllValuesCombinable() // includeInErrorReporting
            ->addBoolDimensionAllValuesCombinable() // captureErrorsConfigOptVal
            ->build();

        return self::adaptToSmoke($result);
    }

    /**
     * @dataProvider dataProviderForTestPhpErrorUndefinedVariable
     *
     * @param bool $includeInErrorReporting
     * @param bool $captureErrorsConfigOptVal
     */
    public function testPhpErrorUndefinedVariable(bool $includeInErrorReporting, bool $captureErrorsConfigOptVal): void
    {
        $testCaseHandle = $this->getTestCaseHandle();
        $appCodeHost = self::ensureMainAppCodeHost($testCaseHandle, $captureErrorsConfigOptVal);
        $appCodeHost->sendRequest(
            AppCodeTarget::asRouted([__CLASS__, 'appCodeForTestPhpErrorUndefinedVariableWrapper']),
            function (AppCodeRequestParams $appCodeRequestParams) use ($includeInErrorReporting): void {
                $appCodeRequestParams->setAppCodeArgs([self::INCLUDE_IN_ERROR_REPORTING => $includeInErrorReporting]);
            }
        );

        $isErrorExpected = $captureErrorsConfigOptVal || (!$includeInErrorReporting);
        $expectedErrorCount = $isErrorExpected ? 1 : 0;
        $dataFromAgent = $testCaseHandle->waitForDataFromAgent(
            (new ExpectedEventCounts())->transactions(1)->errors($expectedErrorCount)
        );
        self::assertCount($expectedErrorCount, $dataFromAgent->idToError);
        if (!$isErrorExpected) {
            return;
        }

        $actualError = $this->verifyError($dataFromAgent);
        if (!$includeInErrorReporting) {
            self::verifySubstituteError($actualError);
            return;
        }

        $expectedCode = self::undefinedVariablePhpErrorCode();
        $expectedType = PhpErrorUtil::getTypeName($expectedCode);

        $dbgCtx = [
            'expectedCode'  => $expectedCode,
            'expectedType'  => $expectedType,
            'actualError'   => $actualError,
            'dataFromAgent' => $dataFromAgent,
        ];
        $dbgCtxStr = LoggableToString::convert($dbgCtx);

        $appCodeFile = FileUtilForTests::listToPath([dirname(__FILE__), 'appCodeForTestPhpErrorUndefinedVariable.php']);
        self::assertNotNull($actualError->exception);
        // From PHP 7.4.x to PHP 8.0.x attempting to read an undefined variable
        // was converted from notice to warning
        // https://www.php.net/manual/en/migration80.incompatible.php
        self::assertNotNull($expectedType, $dbgCtxStr);
        self::assertSame($expectedType, $actualError->exception->type, $dbgCtxStr);
        self::assertSame($expectedCode, $actualError->exception->code, $dbgCtxStr);
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
        self::assertSame($expectedMessage, $actualError->exception->message, $dbgCtxStr);
        self::assertNull($actualError->exception->module, $dbgCtxStr);
        $culpritFunction = __NAMESPACE__ . '\\appCodeForTestPhpErrorUndefinedVariableImpl()';
        self::assertSame($culpritFunction, $actualError->culprit, $dbgCtxStr);

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
        self::verifyAppCodeStacktraceTop($expectedStacktraceTop, $actualError);
    }

    public static function appCodeForTestPhpErrorUncaughtExceptionWrapper(): void
    {
        appCodeForTestPhpErrorUncaughtException();
    }

    /**
     * @dataProvider boolDataProviderAdaptedToSmoke
     *
     * @param bool $captureErrorsConfigOptVal
     */
    public function testPhpErrorUncaughtException(bool $captureErrorsConfigOptVal): void
    {
        $testCaseHandle = $this->getTestCaseHandle();
        $appCodeHost = self::ensureMainAppCodeHost($testCaseHandle, $captureErrorsConfigOptVal);
        $appCodeHost->sendRequest(
            AppCodeTarget::asRouted([__CLASS__, 'appCodeForTestPhpErrorUncaughtExceptionWrapper']),
            function (AppCodeRequestParams $appCodeRequestParams): void {
                if ($appCodeRequestParams instanceof HttpAppCodeRequestParams) {
                    /**
                     * It seems it depends on 'display_errors' whether 200 or 500 is returned on PHP error.
                     * We ignore HTTP status since it's not the main point of the test case.
                     * For more details see https://bugs.php.net/bug.php?id=50921
                     */
                    $appCodeRequestParams->expectedHttpResponseStatusCode = null;
                }
            }
        );

        $isErrorExpected = $captureErrorsConfigOptVal;
        $expectedErrorCount = $isErrorExpected ? 1 : 0;
        $dataFromAgent = $testCaseHandle->waitForDataFromAgent(
            (new ExpectedEventCounts())->transactions(1)->errors($expectedErrorCount)
        );
        self::assertCount($expectedErrorCount, $dataFromAgent->idToError);
        if (!$isErrorExpected) {
            return;
        }

        $err = $this->verifyError($dataFromAgent);

        $appCodeFile = FileUtilForTests::listToPath([dirname(__FILE__), 'appCodeForTestPhpErrorUncaughtException.php']);
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

    public static function appCodeForTestCaughtExceptionResponded500Wrapper(): void
    {
        appCodeForTestCaughtExceptionResponded500();
    }

    private static function ensureMainAppCodeHost(
        TestCaseHandle $testCaseHandle,
        bool $captureErrorsConfigOptVal
    ): AppCodeHostHandle {
        return $testCaseHandle->ensureMainAppCodeHost(
            function (AppCodeHostParams $appCodeParams) use ($captureErrorsConfigOptVal): void {
                if (!self::equalsConfigDefaultValue(OptionNames::CAPTURE_ERRORS, $captureErrorsConfigOptVal)) {
                    $appCodeParams->setAgentOption(OptionNames::CAPTURE_ERRORS, $captureErrorsConfigOptVal);
                }
            }
        );
    }

    /**
     * @dataProvider boolDataProviderAdaptedToSmoke
     *
     * @param bool $captureErrorsConfigOptVal
     */
    public function testCaughtExceptionResponded500(bool $captureErrorsConfigOptVal): void
    {
        $testCaseHandle = $this->getTestCaseHandle();
        $appCodeHost = self::ensureMainAppCodeHost($testCaseHandle, $captureErrorsConfigOptVal);
        $appCodeHost->sendRequest(
            AppCodeTarget::asRouted([__CLASS__, 'appCodeForTestCaughtExceptionResponded500Wrapper']),
            function (AppCodeRequestParams $appCodeRequestParams): void {
                if ($appCodeRequestParams instanceof HttpAppCodeRequestParams) {
                    $appCodeRequestParams->expectedHttpResponseStatusCode
                        = HttpConstantsForTests::STATUS_INTERNAL_SERVER_ERROR;
                }
            }
        );
        $isErrorExpected = self::isMainAppCodeHostHttp() && $captureErrorsConfigOptVal;
        $expectedErrorCount = $isErrorExpected ? 1 : 0;
        $dataFromAgent = $testCaseHandle->waitForDataFromAgent(
            (new ExpectedEventCounts())->transactions(1)->errors($expectedErrorCount)
        );
        self::assertCount($expectedErrorCount, $dataFromAgent->idToError);
        if (!$isErrorExpected) {
            return;
        }

        $err = $this->verifyError($dataFromAgent);

        $appCodeFile = FileUtilForTests::listToPath(
            [dirname(__FILE__), 'appCodeForTestCaughtExceptionResponded500.php']
        );
        self::assertNotNull($err->exception);
        self::assertSame(APP_CODE_FOR_TEST_CAUGHT_EXCEPTION_RESPONDED_500_CODE, $err->exception->code);

        $exceptionNamespace = '';
        $exceptionClassName = '';
        ClassNameUtil::splitFqClassName(
            DummyExceptionForTests::class, /* <- ref */
            $exceptionNamespace, /* <- ref */
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
}
