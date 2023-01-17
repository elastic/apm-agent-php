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

namespace ElasticApmTests\Util\Deserialization;

use Elastic\Apm\Impl\StackTraceFrame;
use ElasticApmTests\Util\AssertValidTrait;
use PHPUnit\Framework\TestCase;

final class StacktraceDeserializer
{
    use AssertValidTrait;

    /**
     * @param mixed $value
     *
     * @return StackTraceFrame[]
     */
    public static function deserialize($value): array
    {
        $deserializedRawData = DeserializationUtil::assertDecodedJsonMap($value);
        /** @var StackTraceFrame[] */
        $frames = [];
        /** @var int */
        $nextExpectedIndex = 0;

        TestCase::assertIsArray($deserializedRawData);
        /** @var array<mixed, mixed> $deserializedRawData */

        foreach ($deserializedRawData as $key => $value) {
            TestCase::assertSame($nextExpectedIndex, $key);
            /** @var array<string, mixed> $value */
            $frames[] = self::deserializeFrame($value);
            ++$nextExpectedIndex;
        }

        self::assertValidStacktrace($frames);
        return $frames;
    }

    /**
     * @param mixed $deserializedRawData
     *
     * @return StackTraceFrame
     */
    private static function deserializeFrame($deserializedRawData): StackTraceFrame
    {
        /** @var ?string */
        $filename = null;
        /** @var int */
        $lineNumber = -1;
        /** @var string|null */
        $function = null;

        TestCase::assertIsArray($deserializedRawData);
        /** @var array<mixed, mixed> $deserializedRawData */
        foreach ($deserializedRawData as $key => $value) {
            switch ($key) {
                case 'filename':
                    $filename = self::assertValidStacktraceFrameFilename($value);
                    break;

                case 'function':
                    $function = self::assertValidStacktraceFrameFilename($value);
                    break;

                case 'lineno':
                    $lineNumber = self::assertValidStacktraceFrameLineNumber($value);
                    break;

                default:
                    throw DeserializationUtil::buildException("Unknown key: span_count->`$key'");
            }
        }
        TestCase::assertNotNull($filename);
        TestCase::assertNotSame(-1, $lineNumber);

        $result = new StackTraceFrame($filename, $lineNumber);
        $result->function = $function;

        return $result;
    }
}
