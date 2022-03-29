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

namespace Elastic\Apm\Impl;

use Elastic\Apm\Impl\Util\ArrayUtil;
use Elastic\Apm\Impl\Util\StaticClassTrait;
use Elastic\Apm\SpanInterface;
use Elastic\Apm\TransactionInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class StacktraceUtil
{
    use StaticClassTrait;

    /**
     * @param int  $numberOfStackFramesToSkip
     * @param bool $hideElasticApmImpl
     *
     * @return StacktraceFrame[]
     */
    public static function captureCurrent(int $numberOfStackFramesToSkip, bool $hideElasticApmImpl): array
    {
        return self::convertFromPhp(
            debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS),
            $numberOfStackFramesToSkip + 1,
            $hideElasticApmImpl
        );
    }

    /**
     * @param array<string, mixed>[] $srcFrames
     * @param int                    $numberOfStackFramesToSkip
     * @param bool                   $hideElasticApmImpl
     *
     * @return StacktraceFrame[]
     */
    public static function convertFromPhp(
        array $srcFrames,
        int $numberOfStackFramesToSkip = 0,
        bool $hideElasticApmImpl = false
    ): array {
        /** @var StacktraceFrame[] */
        $dstFrames = [];
        for ($i = $numberOfStackFramesToSkip; $i < count($srcFrames); ++$i) {
            $srcFrame = $srcFrames[$i];

            $dstFrame = new StacktraceFrame(
                ArrayUtil::getStringValueIfKeyExistsElse('file', $srcFrame, 'FILE NAME N/A'),
                ArrayUtil::getIntValueIfKeyExistsElse('line', $srcFrame, 0)
            );

            $className = ArrayUtil::getValueIfKeyExistsElse('class', $srcFrame, null);
            if ($hideElasticApmImpl && !is_null($className)) {
                if ($className === Span::class) {
                    $className = SpanInterface::class;
                } elseif ($className === Transaction::class) {
                    $className = TransactionInterface::class;
                }
            }
            $funcName = ArrayUtil::getValueIfKeyExistsElse('function', $srcFrame, null);
            $callType = ArrayUtil::getValueIfKeyExistsElse('type', $srcFrame, '.');
            $dstFrame->function = is_null($className)
                ? is_null($funcName) ? null : ($funcName . '()')
                : (($className . $callType) . (is_null($funcName) ? 'FUNCTION NAME N/A' : ($funcName . '()')));

            $dstFrames[] = $dstFrame;
        }

        return $dstFrames;
    }
}
