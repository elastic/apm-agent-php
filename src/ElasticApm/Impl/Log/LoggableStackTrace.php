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

use Elastic\Apm\Impl\Util\ClassicFormatStackTraceFrame;
use Elastic\Apm\Impl\Util\ClassNameUtil;
use Elastic\Apm\Impl\Util\StackTraceUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class LoggableStackTrace
{
    public const STACK_TRACE_KEY = 'stacktrace';

    /**
     * @param int $numberOfStackFramesToSkip
     *
     * @return ClassicFormatStackTraceFrame[]
     */
    public static function buildForCurrent(int $numberOfStackFramesToSkip): array
    {
        /**
         * @param string $key
         * @param mixed  $value
         * @param array  $resultFrame
         */
        $setIfNotNull = function (string $key, $value, array &$resultFrame): void {
            if ($value != null) {
                $resultFrame[$key] = $value;
            }
        };

        $classicFormatFrames = StackTraceUtil::captureInClassicFormat(
            null /* <- loggerFactory */,
            $numberOfStackFramesToSkip + 1 /* <- offset */,
            DEBUG_BACKTRACE_IGNORE_ARGS /* <- options */,
            100 /* limit */
        );
        $result = [];

        foreach ($classicFormatFrames as $classicFormatFrame) {
            $resultFrame = [];
            $adaptFilePath = self::adaptSourceCodeFilePath($classicFormatFrame->file);
            $setIfNotNull(StackTraceUtil::FILE_KEY, $adaptFilePath, $resultFrame);
            $setIfNotNull(StackTraceUtil::LINE_KEY, $classicFormatFrame->line, $resultFrame);
            if ($classicFormatFrame->class !== null) {
                $classShortName = ClassNameUtil::fqToShort($classicFormatFrame->class); // @phpstan-ignore-line
                $resultFrame[StackTraceUtil::CLASS_KEY] = $classShortName;
            }
            $setIfNotNull(StackTraceUtil::FUNCTION_KEY, $classicFormatFrame->function, $resultFrame);
            $result[] = $resultFrame;
        }
        return $result;
    }

    public static function adaptSourceCodeFilePath(?string $srcFile): ?string
    {
        return $srcFile === null ? null : basename($srcFile);
    }
}
