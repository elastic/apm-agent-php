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
use Elastic\Apm\Impl\StackTraceFrame;
use Elastic\Apm\Impl\Util\ArrayUtil;
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
use ElasticApmTests\Util\AssertMessageStack;
use ElasticApmTests\Util\DataFromAgent;
use ElasticApmTests\Util\DataProviderForTestBuilder;
use ElasticApmTests\Util\DummyExceptionForTests;
use ElasticApmTests\Util\ErrorDto;
use ElasticApmTests\Util\FileUtilForTests;
use ElasticApmTests\Util\MixedMap;
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

    private const INCLUDE_IN_ERROR_REPORTING_KEY = 'include_in_error_reporting';
    private const CAPTURE_ERRORS_KEY = 'capture_errors';
    private const CAPTURE_EXCEPTIONS_KEY = 'capture_exceptions';

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
    private static function verifyStackTraceFrameProperty(array $expectedFrame, string $expKey, $actualValue): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, AssertMessageStack::funcArgs());

        if (ArrayUtil::getValueIfKeyExists($expKey, $expectedFrame, /* out */ $expectedValue)) {
            self::assertSame($expectedValue, $actualValue, $expKey);
        }
    }

    /**
     * @param array<string, mixed>[] $expectedStackTraceTop
     * @param ErrorDto               $err
     *
     * @return void
     */
    private static function verifyAppCodeStackTraceTop(array $expectedStackTraceTop, ErrorDto $err): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, AssertMessageStack::funcArgs());

        self::assertNotNull($err->exception);
        $actualStackTrace = $err->exception->stacktrace;
        self::assertNotNull($actualStackTrace);
        self::assertNotEmpty($actualStackTrace);
        self::assertGreaterThanOrEqual(count($expectedStackTraceTop), count($actualStackTrace));

        /** @var StackTraceFrame $bottomFrame */
        $bottomFrame = ArrayUtilForTests::getLastValue($actualStackTrace);
        self::assertSame('ElasticApmTests\\ComponentTests\\Util\\AppCodeHostBase::run', $bottomFrame->function);

        foreach (RangeUtil::generate(0, count($expectedStackTraceTop)) as $frameIndex) {
            $expectedFrame = $expectedStackTraceTop[$frameIndex];
            $actualFrame = $actualStackTrace[$frameIndex];

            self::verifyStackTraceFrameProperty($expectedFrame, self::STACK_TRACE_FILE_NAME, $actualFrame->filename);
            self::verifyStackTraceFrameProperty($expectedFrame, self::STACK_TRACE_FUNCTION, $actualFrame->function);
            self::verifyStackTraceFrameProperty($expectedFrame, self::STACK_TRACE_LINE_NUMBER, $actualFrame->lineno);
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

    public static function appCodeForTestPhpErrorUndefinedVariableWrapper(MixedMap $appCodeArgs): void
    {
        $includeInErrorReporting = $appCodeArgs->getBool(self::INCLUDE_IN_ERROR_REPORTING_KEY);

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
     * @return iterable<string, array{MixedMap}>
     */
    public function dataProviderForTestPhpErrorUndefinedVariable(): iterable
    {
        $result = (new DataProviderForTestBuilder())
            ->addBoolKeyedDimensionAllValuesCombinable(self::INCLUDE_IN_ERROR_REPORTING_KEY)
            ->addBoolKeyedDimensionAllValuesCombinable(self::CAPTURE_ERRORS_KEY)
            ->build();

        return DataProviderForTestBuilder::convertEachDataSetToMixedMap(self::adaptKeyValueToSmoke($result));
    }

    private function implTestPhpErrorUndefinedVariable(MixedMap $testArgs): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, AssertMessageStack::funcArgs());

        $includeInErrorReporting = $testArgs->getBool(self::INCLUDE_IN_ERROR_REPORTING_KEY);
        $captureErrorsConfigOptVal = $testArgs->getBool(self::CAPTURE_ERRORS_KEY);

        $testCaseHandle = $this->getTestCaseHandle();
        $appCodeHost = self::ensureMainAppCodeHost($testCaseHandle, $testArgs);
        $appCodeHost->sendRequest(
            AppCodeTarget::asRouted([__CLASS__, 'appCodeForTestPhpErrorUndefinedVariableWrapper']),
            function (AppCodeRequestParams $appCodeRequestParams) use ($includeInErrorReporting): void {
                $appCodeRequestParams->setAppCodeArgs([self::INCLUDE_IN_ERROR_REPORTING_KEY => $includeInErrorReporting]);
            }
        );

        $isErrorExpected = $captureErrorsConfigOptVal || (!$includeInErrorReporting);
        $expectedErrorCount = $isErrorExpected ? 1 : 0;
        $dataFromAgent = $testCaseHandle->waitForDataFromAgent((new ExpectedEventCounts())->transactions(1)->errors($expectedErrorCount));
        $dbgCtx->add(['dataFromAgent' => $dataFromAgent]);
        self::assertCount($expectedErrorCount, $dataFromAgent->idToError);
        if (!$isErrorExpected) {
            return;
        }

        $actualError = $this->verifyError($dataFromAgent);
        $dbgCtx->add(['actualError' => $actualError]);
        if (!$includeInErrorReporting) {
            self::verifySubstituteError($actualError);
            return;
        }

        $expectedCode = self::undefinedVariablePhpErrorCode();
        $dbgCtx->add(['expectedCode' => $expectedCode]);
        $expectedType = PhpErrorUtil::getTypeName($expectedCode);
        $dbgCtx->add(['expectedType' => $expectedType]);

        $appCodeFile = FileUtilForTests::listToPath([dirname(__FILE__), 'appCodeForTestPhpErrorUndefinedVariable.php']);
        self::assertNotNull($actualError->exception);
        // From PHP 7.4.x to PHP 8.0.x attempting to read an undefined variable
        // was converted from notice to warning
        // https://www.php.net/manual/en/migration80.incompatible.php
        self::assertNotNull($expectedType);
        self::assertSame($expectedType, $actualError->exception->type);
        self::assertSame($expectedCode, $actualError->exception->code);
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
        self::assertSame($expectedMessage, $actualError->exception->message);
        self::assertNull($actualError->exception->module);
        $culpritFunction = __NAMESPACE__ . '\\appCodeForTestPhpErrorUndefinedVariableImpl';
        self::assertSame($culpritFunction, $actualError->culprit);

        $expectedStackTraceTop = [
            [
                self::STACK_TRACE_FILE_NAME   => $appCodeFile,
                self::STACK_TRACE_FUNCTION    => $culpritFunction,
                self::STACK_TRACE_LINE_NUMBER =>
                    APP_CODE_FOR_TEST_PHP_ERROR_UNDEFINED_VARIABLE_CALL_TO_IMPL_LINE_NUMBER,
            ],
            [
                self::STACK_TRACE_FILE_NAME => __FILE__,
                self::STACK_TRACE_FUNCTION  =>
                    __NAMESPACE__ . '\\appCodeForTestPhpErrorUndefinedVariable',
            ],
            [
                self::STACK_TRACE_FUNCTION =>
                    __CLASS__ . '::appCodeForTestPhpErrorUndefinedVariableWrapper',
            ],
        ];
        self::verifyAppCodeStackTraceTop($expectedStackTraceTop, $actualError);
    }

    /**
     * @dataProvider dataProviderForTestPhpErrorUndefinedVariable
     */
    public function testPhpErrorUndefinedVariable(MixedMap $testArgs): void
    {
        self::runAndEscalateLogLevelOnFailure(
            self::buildDbgDescForTestWithArtgs(__CLASS__, __FUNCTION__, $testArgs),
            function () use ($testArgs): void {
                $this->implTestPhpErrorUndefinedVariable($testArgs);
            }
        );
    }

    public static function appCodeForTestPhpErrorUncaughtExceptionWrapper(bool $justReturnLineNumber = false): int
    {
        $callLineNumber = __LINE__ + 1;
        return $justReturnLineNumber ? $callLineNumber : appCodeForTestPhpErrorUncaughtException();
    }


    /**
     * @return iterable<string, array{MixedMap}>
     */
    public function dataProviderCaptureErrorsExceptions(): iterable
    {
        $result = (new DataProviderForTestBuilder())
            ->addBoolKeyedDimensionAllValuesCombinable(self::CAPTURE_ERRORS_KEY)
            ->addKeyedDimensionAllValuesCombinable(self::CAPTURE_EXCEPTIONS_KEY, [null, false, true])
            ->build();

        return DataProviderForTestBuilder::convertEachDataSetToMixedMap(self::adaptKeyValueToSmoke($result));
    }

    private function implTestPhpErrorUncaughtException(MixedMap $testArgs): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, AssertMessageStack::funcArgs());

        $captureErrorsOptVal = $testArgs->getBool(self::CAPTURE_ERRORS_KEY);
        $captureExceptionsOptVal = $testArgs->getNullableBool(self::CAPTURE_EXCEPTIONS_KEY);

        $testCaseHandle = $this->getTestCaseHandle();
        $appCodeHost = self::ensureMainAppCodeHost($testCaseHandle, $testArgs);

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

        $isErrorExpected = $captureErrorsOptVal && ($captureExceptionsOptVal !== false);
        $expectedErrorCount = $isErrorExpected ? 1 : 0;
        $dataFromAgent = $testCaseHandle->waitForDataFromAgent((new ExpectedEventCounts())->transactions(1)->errors($expectedErrorCount));
        $dbgCtx->add(['dataFromAgent' => $dataFromAgent]);
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
        $culpritFunction = __NAMESPACE__ . '\\appCodeForTestPhpErrorUncaughtExceptionImpl';
        self::assertSame($culpritFunction, $err->culprit);

        $expectedStackTraceTop = [
            [
                self::STACK_TRACE_FILE_NAME   => $appCodeFile,
                self::STACK_TRACE_FUNCTION    => null,
                self::STACK_TRACE_LINE_NUMBER => APP_CODE_FOR_TEST_PHP_ERROR_UNCAUGHT_EXCEPTION_ERROR_LINE_NUMBER,
            ],
            [
                self::STACK_TRACE_FILE_NAME   => $appCodeFile,
                self::STACK_TRACE_FUNCTION    => __NAMESPACE__ . '\\appCodeForTestPhpErrorUncaughtExceptionImpl',
                self::STACK_TRACE_LINE_NUMBER => APP_CODE_FOR_TEST_PHP_ERROR_UNCAUGHT_EXCEPTION_CALL_TO_IMPL_LINE_NUMBER,
            ],
            [
                self::STACK_TRACE_FILE_NAME   => __FILE__,
                self::STACK_TRACE_FUNCTION    => __NAMESPACE__ . '\\appCodeForTestPhpErrorUncaughtException',
                self::STACK_TRACE_LINE_NUMBER => self::appCodeForTestPhpErrorUncaughtExceptionWrapper(/* justReturnLineNumber */ true),
            ],
            [
                self::STACK_TRACE_FUNCTION => __CLASS__ . '::appCodeForTestPhpErrorUncaughtExceptionWrapper',
            ],
        ];
        self::verifyAppCodeStackTraceTop($expectedStackTraceTop, $err);
    }

    /**
     * @dataProvider dataProviderCaptureErrorsExceptions
     */
    public function testPhpErrorUncaughtException(MixedMap $testArgs): void
    {
        self::runAndEscalateLogLevelOnFailure(
            self::buildDbgDescForTestWithArtgs(__CLASS__, __FUNCTION__, $testArgs),
            function () use ($testArgs): void {
                $this->implTestPhpErrorUncaughtException($testArgs);
            }
        );
    }

    public static function appCodeForTestCaughtExceptionResponded500Wrapper(bool $justReturnLineNumber = false): int
    {
        $callLineNumber = __LINE__ + 1;
        return $justReturnLineNumber ? $callLineNumber : appCodeForTestCaughtExceptionResponded500();
    }

    private static function ensureMainAppCodeHost(TestCaseHandle $testCaseHandle, MixedMap $testArgs): AppCodeHostHandle
    {
        return $testCaseHandle->ensureMainAppCodeHost(
            function (AppCodeHostParams $appCodeParams) use ($testArgs): void {
                foreach ([OptionNames::CAPTURE_ERRORS, OptionNames::CAPTURE_EXCEPTIONS] as $optName) {
                    if ($testArgs->hasKey($optName)) {
                        $appCodeParams->setAgentOptionIfNotDefaultValue($optName, $testArgs->get($optName));
                    }
                }
            }
        );
    }

    /**
     * @dataProvider dataProviderCaptureErrorsExceptions
     */
    public function testCaughtExceptionResponded500(MixedMap $testArgs): void
    {
        $testCaseHandle = $this->getTestCaseHandle();
        $appCodeHost = self::ensureMainAppCodeHost($testCaseHandle, $testArgs);
        $appCodeHost->sendRequest(
            AppCodeTarget::asRouted([__CLASS__, 'appCodeForTestCaughtExceptionResponded500Wrapper']),
            function (AppCodeRequestParams $appCodeRequestParams): void {
                if ($appCodeRequestParams instanceof HttpAppCodeRequestParams) {
                    $appCodeRequestParams->expectedHttpResponseStatusCode = HttpConstantsForTests::STATUS_INTERNAL_SERVER_ERROR;
                }
            }
        );

        $dataFromAgent = $testCaseHandle->waitForDataFromAgent((new ExpectedEventCounts())->transactions(1));
        self::assertEmpty($dataFromAgent->idToError);
    }
}
