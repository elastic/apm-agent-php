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

use Elastic\Apm\Impl\StackTraceFrame;
use Elastic\Apm\Impl\Util\ClassicFormatStackTraceFrame;
use Elastic\Apm\Impl\Util\StackTraceUtil;

/**
 * @extends ExpectationsBase<StackTraceFrame>
 */
final class StackTraceFrameExpectations extends ExpectationsBase
{
    /** @var Optional<string> */
    public $filename;

    /** @var Optional<?string> */
    public $function;

    /** @var bool */
    public $isFunctionNameRegex = false;

    /** @var Optional<int> */
    public $lineno;

    public function __construct()
    {
        $this->filename = new Optional();
        $this->function = new Optional();
        $this->lineno = new Optional();
    }

    public static function fromFrame(StackTraceFrame $frame): self
    {
        $result = new self();
        $result->filename->setValue($frame->filename);
        $result->function->setValue($frame->function);
        $result->lineno->setValue($frame->lineno);
        return $result;
    }

    private static function buildFunctionFromClassMethod(string $class, bool $isStatic, string $method): string
    {
        return $class . StackTraceUtil::CLASS_AND_METHOD_SEPARATOR . $method;
    }

    public static function fromClassMethod(string $fileName, int $lineNumber, string $class, bool $isStatic, string $method): self
    {
        $result = self::fromClassMethodUnknownLocation($class, $isStatic, $method);
        $result->filename->setValue($fileName);
        $result->lineno->setValue($lineNumber);
        return $result;
    }

    public static function fromClassMethodUnknownLine(string $fileName, string $class, bool $isStatic, string $method): self
    {
        $result = self::fromClassMethodUnknownLocation($class, $isStatic, $method);
        $result->filename->setValue($fileName);
        return $result;
    }

    public static function fromClassMethodUnknownLocation(string $class, bool $isStatic, string $method): self
    {
        $result = new self();
        $result->function->setValue(self::buildFunctionFromClassMethod($class, $isStatic, $method));
        return $result;
    }

    public static function fromClassMethodNoLocation(string $class, bool $isStatic, string $method): self
    {
        $result = self::fromClassMethodUnknownLocation($class, $isStatic, $method);
        $result->filename->setValue(StackTraceUtil::FILE_NAME_NOT_AVAILABLE_SUBSTITUTE);
        $result->lineno->setValue(StackTraceUtil::LINE_NUMBER_NOT_AVAILABLE_SUBSTITUTE);
        return $result;
    }

    public static function fromStandaloneFunction(string $fileName, int $lineNumber, string $func): self
    {
        $result = new self();
        $result->filename->setValue($fileName);
        $result->lineno->setValue($lineNumber);
        $result->function->setValue($func);
        return $result;
    }

    private static function convertFunctionNameToRegPattern(string $text): string
    {
        $result = $text;
        $result = str_replace('\\', '\\\\', $result);
        $result = str_replace('{', '\\{', $result);
        $result = str_replace('}', '\\}', $result);
        $result = str_replace('(', '\\(', $result);
        $result = str_replace(')', '\\)', $result);
        return '/^' . $result . '$/';
    }

    public static function fromClosure(string $fileName, int $lineNumber, ?string $namespace, string $class, bool $isStatic): self
    {
        // Before PHP 8.4: ElasticApmTests\\TestsSharedCode\\SpanStackTraceTestSharedCode::ElasticApmTests\\TestsSharedCode\\{closure}
        // PHP 8.4:        ElasticApmTests\\TestsSharedCode\\SpanStackTraceTestSharedCode::{closure:ElasticApmTests\\TestsSharedCode\\SpanStackTraceTestSharedCode::allSpanCreatingApis():207}
        if (PHP_VERSION_ID < 80400) {
            $result = self::fromClassMethodUnknownLocation($class, $isStatic, ($namespace === null ? '' : ($namespace . '\\')) . '{closure}');
        } else {
            $result = self::fromClassMethodUnknownLocation($class, $isStatic, '{closure:' . $class . '::__METHOD__():__LINE__}');
            $regex = self::convertFunctionNameToRegPattern($result->function->getValue());
            $regex = str_replace('__METHOD__', '[a-zA-Z0-9]+', $regex);
            $regex = str_replace('__LINE__', '[0-9]+', $regex);
            $result->function->setValue($regex);
            $result->isFunctionNameRegex = true;
        }

        $result->filename->setValue($fileName);
        $result->lineno->setValue($lineNumber);
        return $result;
    }

    public static function fromLocationOnly(string $fileName, int $lineNumber): self
    {
        $result = new self();
        $result->filename->setValue($fileName);
        $result->lineno->setValue($lineNumber);
        $result->function->setValue(null);
        return $result;
    }

    public static function fromClassicFormat(ClassicFormatStackTraceFrame $currentFrame, ?ClassicFormatStackTraceFrame $prevFrame): self
    {
        $result = new self();
        $result->filename->setValue($currentFrame->file ?? StackTraceUtil::FILE_NAME_NOT_AVAILABLE_SUBSTITUTE);
        $result->lineno->setValue($currentFrame->line ?? StackTraceUtil::LINE_NUMBER_NOT_AVAILABLE_SUBSTITUTE);
        if ($prevFrame === null) {
            $result->function->setValue(null);
        } else {
            if ($prevFrame->function === null) {
                $result->function->setValue(null);
            } elseif ($prevFrame->class === null || $prevFrame->isStaticMethod === null) {
                $result->function->setValue($prevFrame->function);
            } else {
                $result->function->setValue(self::buildFunctionFromClassMethod($prevFrame->class, $prevFrame->isStaticMethod, $prevFrame->function));
            }
        }
        return $result;
    }

    public function assertMatches(StackTraceFrame $actual): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, AssertMessageStack::funcArgs());
        $dbgCtx->add(['this' => $this]);

        TestCaseBase::assertSameExpectedOptional($this->filename, $actual->filename);
        if ($this->isFunctionNameRegex) {
            if ($this->function->isValueSet()) {
                TestCaseBase::assertMatchesRegularExpression($this->function->getValue(), $actual->function);
            }
        } else {
            TestCaseBase::assertSameExpectedOptional($this->function, $actual->function);
        }
        TestCaseBase::assertSameExpectedOptional($this->lineno, $actual->lineno);
    }
}
