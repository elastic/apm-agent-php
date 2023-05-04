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

namespace Elastic\Apm\Impl\Util;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class DbgUtil
{
    use StaticClassTrait;

    public static function getCallerInfoFromStacktrace(int $numberOfStackFramesToSkip): CallerInfo
    {
        $stackFrames = StackTraceUtil::captureInClassicFormat(/* loggerFactory */ null, /* offset */ $numberOfStackFramesToSkip + 1);

        if (ArrayUtil::isEmpty($stackFrames)) {
            return new CallerInfo(null, null, null, null);
        }

        $stackFrame = $stackFrames[0];
        return new CallerInfo($stackFrame->file, $stackFrame->line, $stackFrame->class, $stackFrame->function);
    }

    /**
     * @param mixed $value
     *
     * @return string
     */
    public static function getType($value): string
    {
        if (is_object($value)) {
            return get_class($value);
        }
        return gettype($value);
    }
}
