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

namespace Elastic\Apm\Impl\Log;

use Elastic\Apm\Impl\Util\ArrayUtil;
use Elastic\Apm\Impl\Util\StackTraceUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class LoggablePhpStacktrace
{
    public const STACK_TRACE_KEY = 'stacktrace';

    /**
     * @param int $numberOfStackFramesToSkip
     *
     * @return array<mixed>
     */
    public static function buildForCurrent(int $numberOfStackFramesToSkip): array
    {
        // #0  c() called at [/tmp/include.php:10]
        // #1  b() called at [/tmp/include.php:6]
        // #2  a() called at [/tmp/include.php:17]

        $result = [];
        /** @noinspection PhpRedundantOptionalArgumentInspection */
        $stackFrames = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT);
        $index = 0;
        foreach ($stackFrames as $stackFrame) {
            ++$index;
            if ($index > $numberOfStackFramesToSkip) {
                $result[] = self::buildStackFrame($stackFrame);
            }
        }
        return $result;
    }

    /**
     * @param array<mixed> $stackFrame
     *
     * @return array<string, mixed>
     */
    private static function buildStackFrame(array $stackFrame): array
    {
        $result = [];
        $className = ArrayUtil::getValueIfKeyExistsElse(StackTraceUtil::CLASS_KEY, $stackFrame, null);
        if (is_string($className)) {
            $result[StackTraceUtil::CLASS_KEY] = $className;
        }

        $funcName = ArrayUtil::getValueIfKeyExistsElse(StackTraceUtil::FUNCTION_KEY, $stackFrame, null);
        if (is_string($funcName)) {
            $result[StackTraceUtil::FUNCTION_KEY] = $funcName;
        }

        if (is_string($srcFile = ArrayUtil::getValueIfKeyExistsElse(StackTraceUtil::FILE_KEY, $stackFrame, null))) {
            $result[StackTraceUtil::FILE_KEY] = self::adaptSourceCodeFilePath($srcFile);
        }

        if (is_int($srcLine = ArrayUtil::getValueIfKeyExistsElse(StackTraceUtil::LINE_KEY, $stackFrame, null))) {
            $result[StackTraceUtil::LINE_KEY] = $srcLine;
        }

        $callThisObj = ArrayUtil::getValueIfKeyExistsElse(StackTraceUtil::THIS_OBJECT_KEY, $stackFrame, null);
        if (is_object($callThisObj)) {
            $result[StackTraceUtil::THIS_OBJECT_KEY] = $callThisObj;
        }

        $callArgs = ArrayUtil::getValueIfKeyExistsElse(StackTraceUtil::ARGS_KEY, $stackFrame, null);
        if (is_iterable($callArgs)) {
            $args = [];
            foreach ($callArgs as $callArg) {
                $args[] = $callArg;
            }
            $result[StackTraceUtil::ARGS_KEY] = $args;
        }

        return $result;
    }

    public static function adaptSourceCodeFilePath(string $srcFile): string
    {
        return basename($srcFile);
    }
}
