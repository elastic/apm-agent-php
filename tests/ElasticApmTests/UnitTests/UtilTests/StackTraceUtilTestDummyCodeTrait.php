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

use Elastic\Apm\Impl\Util\StackTraceUtil;
use ElasticApmTests\UnitTests\Util\SourceCodeLocation;
use ElasticApmTests\Util\StackTraceFrameExpectations;
use ElasticApmTests\Util\TestCaseBase;

trait StackTraceUtilTestDummyCodeTrait
{
    /**
     * @return StackTraceUtilTestDummyCodeCallKind[]
     */
    public function directCallKinds(): array
    {
        $closure = function (StackTraceUtilTestDummyCodeArgs $args): StackTraceUtilTestDummyCodeRetVal {
            $expectations = $args->expectations;
            $thisCallFrameExpectations = StackTraceFrameExpectations::fromClosure($args->calledFrom->fileName, $args->calledFrom->lineNumber, __NAMESPACE__, __CLASS__, /* isStatic */ false);
            $args->addCurrentCallExpectations(/* ref */ $expectations, $thisCallFrameExpectations);

            if (!$args->isThereMoreCallsToMake()) {
                $captureInApmFormatCallLineNumber = __LINE__ + 6;
                $srcLoc = $args->selectCalledFrom(new SourceCodeLocation(__FILE__, $captureInApmFormatCallLineNumber));
                $expectationsForCaptureInApmFormatCall
                    = StackTraceFrameExpectations::fromClassMethod($srcLoc->fileName, $srcLoc->lineNumber, StackTraceUtil::class, /* isStatic */ false, 'captureInApmFormat');
                array_unshift(/* ref */ $expectations, $expectationsForCaptureInApmFormatCall);
                TestCaseBase::assertSame(__LINE__ + 1, $captureInApmFormatCallLineNumber);
                return new StackTraceUtilTestDummyCodeRetVal($expectations, $args->stackTraceUtil->captureInApmFormat(/* offset */ 0, /* maxNumberOfFrames */ null));
            }

            $nextCallData = $args->getNextCallData(new SourceCodeLocation(__FILE__, __LINE__ + 1), $expectations);
            return $nextCallData[0](...$nextCallData[1]);
        };

        return [
            new StackTraceUtilTestDummyCodeCallKind('regular method', __CLASS__, [$this, 'regularMethod']),
            new StackTraceUtilTestDummyCodeCallKind('static method', __CLASS__, [__CLASS__, 'staticMethod']),
            new StackTraceUtilTestDummyCodeCallKind('closure', __CLASS__, $closure),
        ];
    }

    /**
     * @return StackTraceUtilTestDummyCodeCallKind[]
     */
    public function trampolineCallKinds(): array
    {
        return [
            new StackTraceUtilTestDummyCodeCallKind('call_user_func - regular method', __CLASS__, 'call_user_func', /* argsPrefix: */ [[$this, 'regularMethodViaCallUserFunc']]),
            new StackTraceUtilTestDummyCodeCallKind('call_user_func - statuc method', __CLASS__, 'call_user_func', /* argsPrefix: */ [[$this, 'staticMethodViaCallUserFunc']]),
        ];
    }

    /**
     * @return StackTraceUtilTestDummyCodeCallKind[]
     */
    public function callKinds(): array
    {
        return array_merge($this->directCallKinds(), $this->trampolineCallKinds());
    }

    public function regularMethod(StackTraceUtilTestDummyCodeArgs $args): StackTraceUtilTestDummyCodeRetVal
    {
        $expectations = $args->expectations;
        $thisCallFrameExpectations = StackTraceFrameExpectations::fromClassMethod($args->calledFrom->fileName, $args->calledFrom->lineNumber, __CLASS__, /* isStatic */ false, __FUNCTION__);
        $args->addCurrentCallExpectations(/* ref */ $expectations, $thisCallFrameExpectations);

        if (!$args->isThereMoreCallsToMake()) {
            $captureInApmFormatCallLineNumber = __LINE__ + 6;
            $srcLoc = $args->selectCalledFrom(new SourceCodeLocation(__FILE__, $captureInApmFormatCallLineNumber));
            $expectationsForCaptureInApmFormatCall
                = StackTraceFrameExpectations::fromClassMethod($srcLoc->fileName, $srcLoc->lineNumber, StackTraceUtil::class, /* isStatic */ false, 'captureInApmFormat');
            array_unshift(/* ref */ $expectations, $expectationsForCaptureInApmFormatCall);
            TestCaseBase::assertSame(__LINE__ + 1, $captureInApmFormatCallLineNumber);
            return new StackTraceUtilTestDummyCodeRetVal($expectations, $args->stackTraceUtil->captureInApmFormat(/* offset */ 0, /* maxNumberOfFrames */ null));
        }

        $nextCallData = $args->getNextCallData(new SourceCodeLocation(__FILE__, __LINE__ + 1), $expectations);
        return $nextCallData[0](...$nextCallData[1]);
    }

    public static function staticMethod(StackTraceUtilTestDummyCodeArgs $args): StackTraceUtilTestDummyCodeRetVal
    {
        $expectations = $args->expectations;
        $thisCallFrameExpectations = StackTraceFrameExpectations::fromClassMethod($args->calledFrom->fileName, $args->calledFrom->lineNumber, __CLASS__, /* isStatic */ true, __FUNCTION__);
        $args->addCurrentCallExpectations(/* ref */ $expectations, $thisCallFrameExpectations);

        if (!$args->isThereMoreCallsToMake()) {
            $captureInApmFormatCallLineNumber = __LINE__ + 6;
            $srcLoc = $args->selectCalledFrom(new SourceCodeLocation(__FILE__, $captureInApmFormatCallLineNumber));
            $expectationsForCaptureInApmFormatCall
                = StackTraceFrameExpectations::fromClassMethod($srcLoc->fileName, $srcLoc->lineNumber, StackTraceUtil::class, /* isStatic */ false, 'captureInApmFormat');
            array_unshift(/* ref */ $expectations, $expectationsForCaptureInApmFormatCall);
            TestCaseBase::assertSame(__LINE__ + 1, $captureInApmFormatCallLineNumber);
            return new StackTraceUtilTestDummyCodeRetVal($expectations, $args->stackTraceUtil->captureInApmFormat(/* offset */ 0, /* maxNumberOfFrames */ null));
        }

        $nextCallData = $args->getNextCallData(new SourceCodeLocation(__FILE__, __LINE__ + 1), $expectations);
        return $nextCallData[0](...$nextCallData[1]);
    }

    public function regularMethodViaCallUserFunc(StackTraceUtilTestDummyCodeArgs $args): StackTraceUtilTestDummyCodeRetVal
    {
        $expectations = $args->expectations;
        if ($args->isPrevCallToCodeToHide()) {
            $thisCallFrameExpectations = StackTraceFrameExpectations::fromClassMethod($args->calledFrom->fileName, $args->calledFrom->lineNumber, __CLASS__, /* isStatic */ false, __FUNCTION__);
        } else {
            $callUserFuncFrameExpectations = StackTraceFrameExpectations::fromStandaloneFunction($args->calledFrom->fileName, $args->calledFrom->lineNumber, 'call_user_func');
            array_unshift(/* ref */ $expectations, $callUserFuncFrameExpectations);
            $args->calledFrom->fileName = StackTraceUtil::FILE_NAME_NOT_AVAILABLE_SUBSTITUTE;
            $args->calledFrom->lineNumber = StackTraceUtil::LINE_NUMBER_NOT_AVAILABLE_SUBSTITUTE;
            $thisCallFrameExpectations = StackTraceFrameExpectations::fromClassMethodNoLocation(__CLASS__, /* isStatic */ false, __FUNCTION__);
        }
        $args->addCurrentCallExpectations(/* ref */ $expectations, $thisCallFrameExpectations);

        if (!$args->isThereMoreCallsToMake()) {
            $captureInApmFormatCallLineNumber = __LINE__ + 6;
            $srcLoc = $args->selectCalledFrom(new SourceCodeLocation(__FILE__, $captureInApmFormatCallLineNumber));
            $expectationsForCaptureInApmFormatCall
                = StackTraceFrameExpectations::fromClassMethod($srcLoc->fileName, $srcLoc->lineNumber, StackTraceUtil::class, /* isStatic */ false, 'captureInApmFormat');
            array_unshift(/* ref */ $expectations, $expectationsForCaptureInApmFormatCall);
            TestCaseBase::assertSame(__LINE__ + 1, $captureInApmFormatCallLineNumber);
            return new StackTraceUtilTestDummyCodeRetVal($expectations, $args->stackTraceUtil->captureInApmFormat(/* offset */ 0, /* maxNumberOfFrames */ null));
        }

        $nextCallData = $args->getNextCallData(new SourceCodeLocation(__FILE__, __LINE__ + 1), $expectations);
        return $nextCallData[0](...$nextCallData[1]);
    }

    public static function staticMethodViaCallUserFunc(StackTraceUtilTestDummyCodeArgs $args): StackTraceUtilTestDummyCodeRetVal
    {
        $expectations = $args->expectations;
        if ($args->isPrevCallToCodeToHide()) {
            $thisCallFrameExpectations = StackTraceFrameExpectations::fromClassMethod($args->calledFrom->fileName, $args->calledFrom->lineNumber, __CLASS__, /* isStatic */ true, __FUNCTION__);
        } else {
            $callUserFuncFrameExpectations = StackTraceFrameExpectations::fromStandaloneFunction($args->calledFrom->fileName, $args->calledFrom->lineNumber, 'call_user_func');
            array_unshift(/* ref */ $expectations, $callUserFuncFrameExpectations);
            $args->calledFrom->fileName = StackTraceUtil::FILE_NAME_NOT_AVAILABLE_SUBSTITUTE;
            $args->calledFrom->lineNumber = StackTraceUtil::LINE_NUMBER_NOT_AVAILABLE_SUBSTITUTE;
            $thisCallFrameExpectations = StackTraceFrameExpectations::fromClassMethodNoLocation(__CLASS__, /* isStatic */ true, __FUNCTION__);
        }
        $args->addCurrentCallExpectations(/* ref */ $expectations, $thisCallFrameExpectations);

        if (!$args->isThereMoreCallsToMake()) {
            $captureInApmFormatCallLineNumber = __LINE__ + 6;
            $srcLoc = $args->selectCalledFrom(new SourceCodeLocation(__FILE__, $captureInApmFormatCallLineNumber));
            $expectationsForCaptureInApmFormatCall
                = StackTraceFrameExpectations::fromClassMethod($srcLoc->fileName, $srcLoc->lineNumber, StackTraceUtil::class, /* isStatic */ false, 'captureInApmFormat');
            array_unshift(/* ref */ $expectations, $expectationsForCaptureInApmFormatCall);
            TestCaseBase::assertSame(__LINE__ + 1, $captureInApmFormatCallLineNumber);
            return new StackTraceUtilTestDummyCodeRetVal($expectations, $args->stackTraceUtil->captureInApmFormat(/* offset */ 0, /* maxNumberOfFrames */ null));
        }

        $nextCallData = $args->getNextCallData(new SourceCodeLocation(__FILE__, __LINE__ + 1), $expectations);
        return $nextCallData[0](...$nextCallData[1]);
    }
}
