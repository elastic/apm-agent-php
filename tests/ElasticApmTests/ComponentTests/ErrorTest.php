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
use ElasticApmTests\ComponentTests\Util\ComponentTestCaseBase;
use ElasticApmTests\ComponentTests\Util\DataFromAgent;
use ElasticApmTests\ComponentTests\Util\HttpConsts;
use ElasticApmTests\ComponentTests\Util\TestProperties;
use Exception;
use PHPUnit\Framework\TestCase;
use Throwable;

final class ErrorTest extends ComponentTestCaseBase
{
    private const UNDEFINED_VARIABLE_LINE_NUMBER = 65;
    private const UNCAUGHT_EXCEPTION_MESSAGE = 'A message for an uncaught exception';
    private const EXCEPTION_CONVERTED_TO_500_MESSAGE = 'A message for an exception converted to 500';

    private function assertErrorValid(DataFromAgent $dataFromAgent): ErrorData
    {
        $tx = $this->verifyTransactionWithoutSpans($dataFromAgent);
        $err = $dataFromAgent->singleError();

        $this->assertSame($tx->id, $err->transactionId);
        $this->assertSame($tx->id, $err->parentId);
        $this->assertNotNull($err->transaction);
        $this->assertSame($tx->name, $err->transaction->name);
        $this->assertSame($tx->type, $err->transaction->type);
        $this->assertSame($tx->isSampled, $err->transaction->isSampled);

        return $err;
    }

    public static function appCodeForTestPhpErrorUndefinedVariable(): void
    {
        // Ensure E_NOTICE is included in error_reporting
        error_reporting(error_reporting() | E_NOTICE);

        TestCase::assertSame(self::UNDEFINED_VARIABLE_LINE_NUMBER, __LINE__ + 2);
        /** @noinspection PhpUndefinedVariableInspection */
        $undefinedVariable = $undefinedVariable + 1; // @phpstan-ignore-line
    }

    public function testPhpErrorUndefinedVariable(): void
    {
        $appCodeForTestMethod = [__CLASS__, 'appCodeForTestPhpErrorUndefinedVariable'];
        $this->sendRequestToInstrumentedAppAndVerifyDataFromAgent(
            (new TestProperties())
                ->withRoutedAppCode($appCodeForTestMethod),
            function (DataFromAgent $dataFromAgent) use ($appCodeForTestMethod): void {
                $err = $this->assertErrorValid($dataFromAgent);

                $this->assertNotNull($err->exception);
                $this->assertSame(E_NOTICE, $err->exception->code);
                $expectedMessage = __FILE__ . '(' . self::UNDEFINED_VARIABLE_LINE_NUMBER
                                   . '): Undefined variable: undefinedVariable';
                $this->assertSame($expectedMessage, $err->exception->message);
                $this->assertNull($err->exception->module);

                $this->assertNotNull($err->exception->stacktrace);
                $this->assertNotEmpty($err->exception->stacktrace);
                /** @var StacktraceFrame $errStacktraceTopFrame */
                $errStacktraceTopFrame = $err->exception->stacktrace[0];
                // TODO: Sergey Kleyman: Fix
                // $this->assertSame(__FILE__, $errStacktraceTopFrame->filename);
                $expectedFunction = __CLASS__ . '::' . $appCodeForTestMethod[1] . '()';
                $this->assertSame($expectedFunction, $errStacktraceTopFrame->function);
                // TODO: Sergey Kleyman: Fix
                // $this->assertSame(self::UNDEFINED_VARIABLE_LINE_NUMBER, $errStacktraceTopFrame->lineno);

                $this->assertSame('E_NOTICE', $err->exception->type);
            }
        );
    }

    public static function appCodeForTestPhpErrorUncaughtException(): void
    {
        throw new Exception(self::UNCAUGHT_EXCEPTION_MESSAGE);
    }

    public function testPhpErrorUncaughtException(): void
    {
        $appCodeForTestMethod = [__CLASS__, 'appCodeForTestPhpErrorUncaughtException'];
        $this->sendRequestToInstrumentedAppAndVerifyDataFromAgent(
            (new TestProperties())
                ->withRoutedAppCode($appCodeForTestMethod)
                ->withExpectedStatusCode(HttpConsts::STATUS_INTERNAL_SERVE_ERROR),
            function (DataFromAgent $dataFromAgent): void {
                $err = $this->assertErrorValid($dataFromAgent);

                // TODO: Sergey Kleyman: REMOVE
                self::printMessage(__METHOD__, '$err: ' . LoggableToString::convert($err));

                $this->assertNotNull($err->exception);

                $this->assertSame(E_ERROR, $err->exception->code);
                // $expectedMessage = __FILE__ . '(' . self::UNDEFINED_VARIABLE_LINE_NUMBER
                //                    . '): Undefined variable: undefinedVariable';
                // $this->assertSame($expectedMessage, $err->exception->message);
                // $this->assertNull($err->exception->module);
                //
                // $this->assertNotNull($err->exception->stacktrace);
                // $this->assertNotEmpty($err->exception->stacktrace);
                // /** @var StacktraceFrame $errStacktraceTopFrame */
                // $errStacktraceTopFrame = $err->exception->stacktrace[0];
                // // TODO: Sergey Kleyman: Fix
                // // $this->assertSame(__FILE__, $errStacktraceTopFrame->filename);
                // $expectedFunction = __CLASS__ . '::' . $appCodeForTestMethod[1] . '()';
                // $this->assertSame($expectedFunction, $errStacktraceTopFrame->function);
                // // TODO: Sergey Kleyman: Fix
                // // $this->assertSame(self::UNDEFINED_VARIABLE_LINE_NUMBER, $errStacktraceTopFrame->lineno);

                $this->assertSame('E_ERROR', $err->exception->type);
            }
        );
    }

    public static function appCodeForTestCaughtExceptionResponded500Aux(): void
    {
        throw new Exception(self::EXCEPTION_CONVERTED_TO_500_MESSAGE);
    }

    // public static function appCodeForTestCaughtExceptionResponded500(): void
    // {
    //     try {
    //         self::appCodeForTestCaughtExceptionResponded500Aux();
    //     } catch (Throwable $throwable) {
    //         http_response_code(500);
    //     }
    // }
    //
    // public function testCaughtExceptionResponded500(): void
    // {
    //     $appCodeForTestMethod = [__CLASS__, 'appCodeForTestCaughtExceptionResponded500'];
    //     $this->sendRequestToInstrumentedAppAndVerifyDataFromAgent(
    //         (new TestProperties())
    //             ->withRoutedAppCode($appCodeForTestMethod)
    //             ->withExpectedStatusCode(HttpConsts::STATUS_INTERNAL_SERVE_ERROR),
    //         function (DataFromAgent $dataFromAgent): void {
    //             $err = $this->assertErrorValid($dataFromAgent);
    //
    //             // TODO: Sergey Kleyman: REMOVE
    //             self::printMessage(__METHOD__, '$err: ' . LoggableToString::convert($err));
    //
    //             $this->assertNotNull($err->exception);
    //
    //             // $this->assertSame(E_NOTICE, $err->exception->code);
    //             // $expectedMessage = __FILE__ . '(' . self::UNDEFINED_VARIABLE_LINE_NUMBER
    //             //                    . '): Undefined variable: undefinedVariable';
    //             // $this->assertSame($expectedMessage, $err->exception->message);
    //             // $this->assertNull($err->exception->module);
    //             //
    //             // $this->assertNotNull($err->exception->stacktrace);
    //             // $this->assertNotEmpty($err->exception->stacktrace);
    //             // /** @var StacktraceFrame $errStacktraceTopFrame */
    //             // $errStacktraceTopFrame = $err->exception->stacktrace[0];
    //             // // TODO: Sergey Kleyman: Fix
    //             // // $this->assertSame(__FILE__, $errStacktraceTopFrame->filename);
    //             // $expectedFunction = __CLASS__ . '::' . $appCodeForTestMethod[1] . '()';
    //             // $this->assertSame($expectedFunction, $errStacktraceTopFrame->function);
    //             // // TODO: Sergey Kleyman: Fix
    //             // // $this->assertSame(self::UNDEFINED_VARIABLE_LINE_NUMBER, $errStacktraceTopFrame->lineno);
    //             //
    //             // $this->assertSame('E_NOTICE', $err->exception->type);
    //         }
    //     );
    // }
}
