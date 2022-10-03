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

use Elastic\Apm\Impl\Span;
use Elastic\Apm\Impl\StacktraceFrame;
use ElasticApmTests\Util\Deserialization\DeserializationUtil;
use ElasticApmTests\Util\Deserialization\StacktraceDeserializer;

class SpanDto extends ExecutionSegmentDto
{
    /** @var string */
    public $parentId;

    /** @var string */
    public $transactionId;

    /** @var ?string */
    public $action = null;

    /** @var ?string */
    public $subtype = null;

    /** @var null|StacktraceFrame[] */
    public $stacktrace = null;

    /** @var ?SpanContextDto */
    public $context = null;

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
                if (parent::deserializeKeyValue($key, $value, $result)) {
                    return true;
                }

                switch ($key) {
                    case 'action':
                        $result->action = self::assertValidKeywordString($value);
                        return true;
                    case 'context':
                        $result->context = SpanContextDto::deserialize($value);
                        return true;
                    case 'parent_id':
                        $result->parentId = self::assertValidId($value);
                        return true;
                    case 'stacktrace':
                        $result->stacktrace = StacktraceDeserializer::deserialize($value);
                        return true;
                    case 'subtype':
                        $result->subtype = self::assertValidKeywordString($value);
                        return true;
                    case 'transaction_id':
                        $result->transactionId = self::assertValidId($value);
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
        $this->assertMatches(new SpanExpectations());
    }

    public function assertMatches(SpanExpectations $expectations): void
    {
        parent::assertMatchesExecutionSegment($expectations);

        self::assertValidId($this->parentId);
        self::assertValidId($this->transactionId);
        self::assertSameNullableKeywordStringExpectedOptional($expectations->action, $this->action);
        self::assertSameNullableKeywordStringExpectedOptional($expectations->subtype, $this->subtype);
        if ($this->stacktrace !== null) {
            self::assertValidStacktrace($this->stacktrace);
        }
        SpanContextDto::assertNullableMatches($expectations->context, $this->context);
    }

    public function assertEquals(Span $original): void
    {
        self::assertEqualOriginalAndDto($original, $this);
    }
}
