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

use Elastic\Apm\Impl\Transaction;
use ElasticApmTests\Util\Deserialization\DeserializationUtil;
use PHPUnit\Framework\TestCase;

class TransactionDto extends ExecutionSegmentDto
{
    /** @var ?string */
    public $parentId = null;

    /** @var int */
    public $startedSpansCount = 0;

    /** @var int */
    public $droppedSpansCount = 0;

    /** @var ?string */
    public $result = null;

    /** @var bool */
    public $isSampled = true;

    /** @var ?TransactionContextDto */
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
                    case 'context':
                        $result->context = TransactionContextDto::deserialize($value);
                        return true;
                    case 'parent_id':
                        $result->parentId = self::assertValidId($value);
                        return true;
                    case 'result':
                        $result->result = self::assertValidKeywordString($value);
                        return true;
                    case 'span_count':
                        self::deserializeSpanCountSubObject($value, $result);
                        return true;
                    case 'sampled':
                        $result->isSampled = self::assertValidBool($value);
                        return true;
                    default:
                        return false;
                }
            }
        );

        $result->assertValid();
        return $result;
    }

    /**
     * @param mixed $value
     */
    private static function deserializeSpanCountSubObject($value, TransactionDto $result): void
    {
        DeserializationUtil::deserializeKeyValuePairs(
            DeserializationUtil::assertDecodedJsonMap($value),
            function ($key, $value) use ($result): bool {
                switch ($key) {
                    case 'dropped':
                        $result->droppedSpansCount = self::assertValidCount($value);
                        return true;
                    case 'started':
                        $result->startedSpansCount = self::assertValidCount($value);
                        return true;
                    default:
                        return false;
                }
            }
        );
    }

    public function assertValid(): void
    {
        $this->assertMatches(new TransactionExpectations());
    }

    public function assertMatches(TransactionExpectations $expectations): void
    {
        parent::assertMatchesExecutionSegment($expectations);

        if ($this->parentId !== null) {
            self::assertValidId($this->parentId);
        }

        if ($expectations->isSampled !== null) {
            TestCase::assertSame($expectations->isSampled, $this->isSampled);
        }

        self::assertValidCount($this->startedSpansCount);
        self::assertValidCount($this->droppedSpansCount);

        if (!$this->isSampled) {
            TestCase::assertSame(0, $this->startedSpansCount);
            TestCase::assertSame(0, $this->droppedSpansCount);
            TestCase::assertNull($this->context);
        }

        if ($expectations->droppedSpansCount !== null) {
            TestCase::assertSame($expectations->droppedSpansCount, $this->droppedSpansCount);
        }

        if ($this->context !== null) {
            $this->context->assertValid();
        }
    }

    /**
     * @param mixed $count
     *
     * @return int
     */
    public static function assertValidCount($count): int
    {
        TestCase::assertIsInt($count);
        /** @var int $count */
        TestCase::assertGreaterThanOrEqual(0, $count);
        return $count;
    }

    public function assertEquals(Transaction $original): void
    {
        self::assertEqualOriginalAndDto($original, $this);
    }
}
