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
use ElasticApmTests\Util\Deserialization\DeserializationUtil;
use ElasticApmTests\Util\Deserialization\StacktraceDeserializer;

class ErrorExceptionDto
{
    use AssertValidTrait;

    /** @var null|int|string */
    public $code = null;

    /** @var ?string */
    public $message = null;

    /** @var ?string */
    public $module = null;

    /** @var null|StackTraceFrame[] */
    public $stacktrace = null;

    /** @var ?string */
    public $type = null;

    /**
     * @param mixed $value
     *
     * @return self
     */
    public static function deserialize($value): self
    {
        $result = new self();
        DeserializationUtil::deserializeKeyValuePairs(
            DeserializationUtil::assertDecodedJsonMap($value),
            function ($key, $value) use ($result): bool {
                switch ($key) {
                    case 'code':
                        $result->code = self::assertValidCode($value);
                        return true;
                    case 'message':
                        $result->message = self::assertValidNullableNonKeywordString($value);
                        return true;
                    case 'module':
                        $result->module = self::assertValidNullableKeywordString($value);
                        return true;
                    case 'stacktrace':
                        $result->stacktrace = StacktraceDeserializer::deserialize($value);
                        return true;
                    case 'type':
                        $result->type = self::assertValidNullableKeywordString($value);
                        return true;
                    default:
                        return false;
                }
            }
        );

        $result->assertValid();
        return $result;
    }

    public function assertValid(): void
    {
        self::assertValidCode($this->code);
        self::assertValidNullableNonKeywordString($this->message);
        self::assertValidNullableKeywordString($this->module);
        if ($this->stacktrace !== null) {
            self::assertValidStacktrace($this->stacktrace);
        }
        self::assertValidNullableKeywordString($this->type);
    }

    /**
     * @param mixed $value
     *
     * @return int|string|null
     */
    private static function assertValidCode($value)
    {
        if (is_int($value)) {
            return $value;
        }

        return self::assertValidNullableKeywordString($value);
    }
}
