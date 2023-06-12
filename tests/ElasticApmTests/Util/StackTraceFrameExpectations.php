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
        return $class . ($isStatic ? '::' : '->') . $method;
    }

    public static function fromClassMethod(string $fileName, int $lineNumber, string $class, bool $isStatic, string $method): self
    {
        $result = self::fromClassMethodUnknownLocation($class, $isStatic, $method);
        $result->filename->setValue($fileName);
        $result->lineno->setValue($lineNumber);
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

    public static function fromClosure(string $fileName, int $lineNumber, ?string $namespace, string $class, bool $isStatic): self
    {
        $result = self::fromClassMethodUnknownLocation($class, $isStatic, ($namespace === null ? '' : ($namespace . '\\')) . '{closure}');
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

    public function assertMatches(StackTraceFrame $actual): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, AssertMessageStack::funcArgs());
        $dbgCtx->add(['this' => $this]);

        TestCaseBase::assertSameExpectedOptional($this->filename, $actual->filename);
        TestCaseBase::assertSameExpectedOptional($this->function, $actual->function);
        TestCaseBase::assertSameExpectedOptional($this->lineno, $actual->lineno);
    }
}
