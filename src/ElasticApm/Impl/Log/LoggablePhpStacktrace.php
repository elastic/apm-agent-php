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

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class LoggablePhpStacktrace
{
    public const STACK_TRACE_KEY = 'stacktrace';
    public const CLASS_KEY = 'class';
    public const FUNCTION_KEY = 'function';
    public const FILE_KEY = 'file';
    public const LINE_KEY = 'line';
    public const THIS_OBJECT_KEY = 'this';
    public const ARGS_KEY = 'args';

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
        if (!is_null($className = ArrayUtil::getValueIfKeyExistsElse('class', $stackFrame, null))) {
            $result[self::CLASS_KEY] = $className;
        }

        if (!is_null($funcName = ArrayUtil::getValueIfKeyExistsElse('function', $stackFrame, null))) {
            $result[self::FUNCTION_KEY] = $funcName;
        }

        if (!is_null($srcFile = ArrayUtil::getValueIfKeyExistsElse('file', $stackFrame, null))) {
            $result[self::FILE_KEY] = self::adaptSourceCodeFilePath($srcFile);
        }

        if (!is_null($srcLine = ArrayUtil::getValueIfKeyExistsElse('line', $stackFrame, null))) {
            $result[self::LINE_KEY] = $srcLine;
        }

        if (!is_null($callThisObj = ArrayUtil::getValueIfKeyExistsElse('object', $stackFrame, null))) {
            $result[self::THIS_OBJECT_KEY] = $callThisObj;
        }

        if (!is_null($callArgs = ArrayUtil::getValueIfKeyExistsElse('args', $stackFrame, null))) {
            $args = [];
            foreach ($callArgs as $callArg) {
                $args[] = $callArg;
            }
            $result[self::ARGS_KEY] = $args;
        }

        return $result;
    }

    public static function adaptSourceCodeFilePath(string $srcFile): string
    {
        return basename($srcFile);
    }
}
