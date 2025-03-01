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

use Closure;
use Elastic\Apm\Impl\Util\ExceptionUtil;
use Elastic\Apm\Impl\Util\StaticClassTrait;
use ElasticApmTests\Util\TestCaseBase;
use Throwable;

final class DeserializationUtil
{
    use StaticClassTrait;

    /**
     * @param mixed $key
     *
     * @return DeserializationException
     */
    public static function buildUnknownKeyException($key): DeserializationException
    {
        return DeserializationUtil::buildException('Unknown key: ' . $key);
    }

    public static function buildException(?string $msgDetails = null, int $code = 0, ?Throwable $previous = null): DeserializationException
    {
        $msgStart = 'Deserialization failed';
        if ($msgDetails !== null) {
            $msgStart .= ': ';
            $msgStart .= $msgDetails;
        }

        return new DeserializationException(
            ExceptionUtil::buildMessage($msgStart, /* context: */ [], /* numberOfStackFramesToSkip */ 1),
            $code,
            $previous
        );
    }

    /**
     * @param mixed $value
     *
     * @return array<mixed>
     */
    public static function assertDecodedJsonMap($value): array
    {
        TestCaseBase::assertIsArray($value);
        return $value;
    }

    /**
     * @param array<mixed>         $deserializedRawData
     * @param Closure(mixed, mixed): bool $deserializeKeyValuePair
     */
    public static function deserializeKeyValuePairs(array $deserializedRawData, Closure $deserializeKeyValuePair): void
    {
        foreach ($deserializedRawData as $key => $value) {
            if (!$deserializeKeyValuePair($key, $value)) {
                throw DeserializationUtil::buildUnknownKeyException($key);
            }
        }
    }
}
