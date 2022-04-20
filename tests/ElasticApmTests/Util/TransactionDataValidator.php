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

final class TransactionDataValidator extends ExecutionSegmentDataValidator
{
    /** @var TransactionDataExpected */
    protected $expected;

    /** @var TransactionData */
    protected $actual;

    private function __construct(TransactionDataExpected $expected, TransactionData $actual)
    {
        parent::__construct($expected, $actual);

        $this->expected = $expected;
        $this->actual = $actual;
    }

    protected function validateImpl(): void
    {
        parent::validateImpl();

        if ($this->actual->parentId !== null) {
            self::validateId($this->actual->parentId);
        }

        if ($this->expected->isSampled !== null) {
            self::assertSame($this->expected->isSampled, $this->actual->isSampled);
        }

        if (!$this->actual->isSampled) {
            self::assertSame(0, $this->actual->startedSpansCount);
            self::assertSame(0, $this->actual->droppedSpansCount);
            self::assertNull($this->actual->context);
        }

        if ($this->actual->context !== null) {
            self::validateContextData($this->actual->context);
        }
    }

    public static function validate(TransactionData $actual, ?TransactionDataExpected $expected = null): void
    {
        (new self($expected ?? new TransactionDataExpected(), $actual))->validateImpl();
    }

    /**
     * @param mixed $count
     *
     * @return int
     */
    public static function validateCount($count): int
    {
        self::assertIsInt($count);
        /** @var int $count */
        self::assertGreaterThanOrEqual(0, $count);
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

        self::assertIsInt($value);
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
        if ($ctxRequestData->url !== null) {
            self::validateContextRequestUrlData($ctxRequestData->url);
        }
    }

    public static function validateContextData(TransactionContextData $ctxData): void
    {
        parent::validateExecutionSegmentContextData($ctxData);
        if ($ctxData->request !== null) {
            self::validateContextRequestData($ctxData->request);
        }
    }
}
