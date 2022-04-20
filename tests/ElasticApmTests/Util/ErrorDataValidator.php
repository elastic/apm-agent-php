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
use Elastic\Apm\Impl\ErrorData;
use Elastic\Apm\Impl\ErrorExceptionData;
use Elastic\Apm\Impl\ErrorTransactionData;

final class ErrorDataValidator extends EventDataValidator
{
    /** @var ErrorDataExpected */
    protected $expected;

    /** @var ErrorData */
    protected $actual;

    private function __construct(ErrorDataExpected $expected, ErrorData $actual)
    {
        $this->expected = $expected;
        $this->actual = $actual;
    }

    private function validateImpl(): void
    {
        self::validateTimestamp($this->actual->timestamp, $this->expected);
        self::validateId($this->actual->id);

        self::assertSameNullness($this->actual->traceId, $this->actual->transactionId);
        self::assertSameNullness($this->actual->traceId, $this->actual->parentId);
        self::assertSameNullness($this->actual->traceId, $this->actual->transaction);

        if ($this->actual->traceId !== null) {
            self::validateTraceId($this->actual->traceId);
        }
        if ($this->actual->transactionId !== null) {
            ExecutionSegmentDataValidator::validateId($this->actual->transactionId);
        }
        if ($this->actual->parentId !== null) {
            ExecutionSegmentDataValidator::validateId($this->actual->parentId);
        }
        if ($this->actual->transaction !== null) {
            self::validateErrorTransactionDataEx();
        }

        if ($this->actual->context !== null) {
            TransactionDataValidator::validateContextData($this->actual->context);
        }

        self::validateNullableNonKeywordString($this->actual->culprit);

        if ($this->actual->exception !== null) {
            self::validateErrorExceptionData($this->actual->exception);
        }
    }

    public static function validate(ErrorData $actual, ?ErrorDataExpected $expected = null): void
    {
        (new self($expected ?? new ErrorDataExpected(), $actual))->validateImpl();
    }

    /**
     * @param mixed $errorId
     *
     * @return string
     */
    public static function validateId($errorId): string
    {
        return self::validateIdEx($errorId, Constants::ERROR_ID_SIZE_IN_BYTES);
    }

    public function validateErrorTransactionDataEx(): void
    {
        $errorTransactionData = $this->actual->transaction;
        if ($errorTransactionData === null) {
            return;
        }

        self::validateErrorTransactionData($errorTransactionData);

        if ($this->expected->isSampled !== null) {
            self::assertSame($this->expected->isSampled, $errorTransactionData->isSampled);
        }
    }

    public static function validateErrorTransactionData(ErrorTransactionData $errorTransactionData): void
    {
        self::validateKeywordString($errorTransactionData->name);
        self::validateKeywordString($errorTransactionData->type);
    }

    /**
     * @param mixed $value
     *
     * @return int|string|null
     */
    public static function validateErrorExceptionCode($value)
    {
        if (is_int($value)) {
            return $value;
        }

        return self::validateNullableKeywordString($value);
    }

    public static function validateErrorExceptionData(ErrorExceptionData $errorExceptionData): void
    {
        self::validateErrorExceptionCode($errorExceptionData->code);
        self::validateNullableNonKeywordString($errorExceptionData->message);
        self::validateNullableKeywordString($errorExceptionData->module);
        if ($errorExceptionData->stacktrace !== null) {
            self::validateStacktrace($errorExceptionData->stacktrace);
        }
        self::validateNullableKeywordString($errorExceptionData->type);
    }
}
