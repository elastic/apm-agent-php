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

use Elastic\Apm\Impl\Constants;
use Elastic\Apm\Impl\Error;
use ElasticApmTests\Util\Deserialization\DeserializationUtil;

class ErrorDto
{
    use AssertValidTrait;

    /** @var float */
    public $timestamp;

    /** @var string */
    public $id;

    /** @var ?string */
    public $traceId = null;

    /** @var ?string */
    public $transactionId = null;

    /** @var ?string */
    public $parentId = null;

    /** @var ?ErrorTransactionDto */
    public $transaction = null;

    /** @var ?TransactionContextDto */
    public $context = null;

    /** @var ?string */
    public $culprit = null;

    /** @var ?ErrorExceptionDto */
    public $exception = null;

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
                    case 'timestamp':
                        $result->timestamp = self::assertValidTimestamp($value);
                        return true;
                    case 'id':
                        $result->id = self::assertValidId($value);
                        return true;
                    case 'trace_id':
                        $result->traceId = TraceValidator::assertValidId($value);
                        return true;
                    case 'transaction_id':
                        $result->transactionId = ExecutionSegmentDto::assertValidId($value);
                        return true;
                    case 'parent_id':
                        $result->parentId = ExecutionSegmentDto::assertValidId($value);
                        return true;
                    case 'transaction':
                        $result->transaction = ErrorTransactionDto::deserialize($value);
                        return true;
                    case 'context':
                        $result->context = TransactionContextDto::deserialize($value);
                        return true;
                    case 'culprit':
                        $result->culprit = self::assertValidNullableKeywordString($value);
                        return true;
                    case 'exception':
                        $result->exception = ErrorExceptionDto::deserialize($value);
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
        $this->assertMatches(new ErrorExpectations());
    }

    public function assertMatches(ErrorExpectations $expectations): void
    {
        self::assertValidTimestamp($this->timestamp, $expectations);
        self::assertValidId($this->id);

        TestCaseBase::assertSameNullness($this->traceId, $this->transactionId);
        TestCaseBase::assertSameNullness($this->traceId, $this->parentId);
        TestCaseBase::assertSameNullness($this->traceId, $this->transaction);

        if ($this->traceId !== null) {
            TraceValidator::assertValidId($this->traceId);
        }
        if ($this->transactionId !== null) {
            ExecutionSegmentDto::assertValidId($this->transactionId);
        }
        if ($this->parentId !== null) {
            ExecutionSegmentDto::assertValidId($this->parentId);
        }
        ErrorTransactionDto::assertNullableMatches($expectations->transaction, $this->transaction);

        if ($this->context !== null) {
            $this->context->assertValid();
        }

        self::assertValidNullableKeywordString($this->culprit);

        if ($this->exception !== null) {
            $this->exception->assertValid();
        }
    }

    /**
     * @param mixed $errorId
     *
     * @return string
     */
    public static function assertValidId($errorId): string
    {
        return self::assertValidIdEx($errorId, Constants::ERROR_ID_SIZE_IN_BYTES);
    }

    public function assertEquals(Error $original): void
    {
        self::assertEqualOriginalAndDto($original, $this);
    }
}
