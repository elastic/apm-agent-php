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

use Elastic\Apm\Impl\TransactionContextData;
use Elastic\Apm\Impl\TransactionContextRequestData;
use Elastic\Apm\Impl\TransactionContextRequestUrlData;
use Elastic\Apm\Impl\TransactionData;
use PHPUnit\Framework\TestCase;

final class TransactionDataValidator extends ExecutionSegmentDataValidator
{
    /** @var TransactionDataExpectations */
    protected $expectations;

    /** @var TransactionData */
    protected $actual;

    private function __construct(TransactionDataExpectations $expectations, TransactionData $actual)
    {
        parent::__construct($expectations, $actual);

        $this->expectations = $expectations;
        $this->actual = $actual;
    }

    protected function validateImpl(): void
    {
        parent::validateImpl();

        if ($this->actual->parentId !== null) {
            self::validateId($this->actual->parentId);
        }

        if ($this->expectations->isSampled !== null) {
            TestCase::assertSame($this->expectations->isSampled, $this->actual->isSampled);
        }

        if (!$this->actual->isSampled) {
            TestCase::assertSame(0, $this->actual->startedSpansCount);
            TestCase::assertSame(0, $this->actual->droppedSpansCount);
            TestCase::assertNull($this->actual->context);
        }

        if ($this->expectations->droppedSpansCount !== null) {
            TestCase::assertSame($this->expectations->droppedSpansCount, $this->actual->droppedSpansCount);
        }

        if ($this->actual->context !== null) {
            self::validateContextData($this->actual->context);
        }
    }

    public static function validate(TransactionData $actual, ?TransactionDataExpectations $expectations = null): void
    {
        (new self($expectations ?? new TransactionDataExpectations(), $actual))->validateImpl();
    }

    /**
     * @param mixed $count
     *
     * @return int
     */
    public static function validateCount($count): int
    {
        TestCase::assertIsInt($count);
        /** @var int $count */
        TestCase::assertGreaterThanOrEqual(0, $count);
        return $count;
    }

    /**
     * @param mixed $value
     *
     * @return ?int
     */
    public static function validateNullablePort($value): ?int
    {
        if ($value === null) {
            return null;
        }

        TestCase::assertIsInt($value);
        /** @var int $value */
        return $value;
    }

    public static function validateContextRequestUrlData(TransactionContextRequestUrlData $ctxRequestUrlData): void
    {
        self::validateNullableKeywordString($ctxRequestUrlData->domain);
        self::validateNullableKeywordString($ctxRequestUrlData->full);
        self::validateNullableKeywordString($ctxRequestUrlData->original);
        self::validateNullableKeywordString($ctxRequestUrlData->path);
        self::validateNullablePort($ctxRequestUrlData->port);
        self::validateNullableKeywordString($ctxRequestUrlData->protocol);
        self::validateNullableKeywordString($ctxRequestUrlData->query);
    }

    public static function validateContextRequestData(TransactionContextRequestData $ctxRequestData): void
    {
        /**
         * @link https://github.com/elastic/apm-server/blob/v7.0.0/docs/spec/request.json#L101
         * "required": ["url", "method"]
         */
        self::validateKeywordString($ctxRequestData->method);
        TestCase::assertNotNull($ctxRequestData->url);
        self::validateContextRequestUrlData($ctxRequestData->url);
    }

    public static function validateContextData(TransactionContextData $ctxData): void
    {
        parent::validateExecutionSegmentContextData($ctxData);
        if ($ctxData->request !== null) {
            self::validateContextRequestData($ctxData->request);
        }
    }
}
