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

use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableToString;
use Elastic\Apm\Impl\Log\LoggableTrait;
use Elastic\Apm\Impl\Metadata;
use Elastic\Apm\Impl\MetricSet;
use Elastic\Apm\Impl\Util\ArrayUtil;
use ElasticApmTests\ComponentTests\Util\ApmDataKind;
use PHPUnit\Framework\TestCase;

class DataFromAgent implements LoggableInterface
{
    use LoggableTrait;

    /** @var Metadata[] */
    public $metadatas = [];

    /** @var array<string, TransactionDto> */
    public $idToTransaction = [];

    /** @var array<string, SpanDto> */
    public $idToSpan = [];

    /** @var array<string, ErrorDto> */
    public $idToError = [];

    /** @var MetricSet[] */
    public $metricSets = [];

    /**
     * @template EventType
     *
     * @param EventType[] $events
     *
     * @return EventType
     */
    private static function singleEvent(array $events)
    {
        return ArrayUtilForTests::getSingleValue($events);
    }

    /**
     * @return TransactionDto
     */
    public function singleTransaction(): TransactionDto
    {
        return self::singleEvent($this->idToTransaction);
    }

    /**
     * @return SpanDto
     */
    public function singleSpan(): SpanDto
    {
        return self::singleEvent($this->idToSpan);
    }

    public function singleError(): ErrorDto
    {
        return self::singleEvent($this->idToError);
    }

    /**
     * @param string $name
     *
     * @return SpanDto[]
     */
    public function findSpansByName(string $name): array
    {
        $result = [];
        foreach ($this->idToSpan as $span) {
            if ($span->name === $name) {
                $result[] = $span;
            }
        }
        return $result;
    }

    /**
     * @param string $name
     *
     * @return SpanDto
     */
    public function singleSpanByName(string $name): SpanDto
    {
        $spans = $this->findSpansByName($name);
        TestCase::assertCount(
            1,
            $spans,
            LoggableToString::convert(['name' => $name, 'spans' => $spans, 'this' => $this])
        );
        return $spans[0];
    }

    /**
     * @param TransactionDto $transaction
     *
     * @return array<string, SpanDto>
     */
    public function spansForTransaction(TransactionDto $transaction): array
    {
        $idToSpanForTransaction = [];

        foreach ($this->idToSpan as $span) {
            if ($span->transactionId === $transaction->id) {
                $idToSpanForTransaction[$span->id] = $span;
            }
        }

        return $idToSpanForTransaction;
    }

    /**
     * @param TransactionDto $transaction
     *
     * @return array<string, ErrorDto>
     */
    public function errorsForTransaction(TransactionDto $transaction): array
    {
        $idToErrorForTransaction = [];

        foreach ($this->idToError as $error) {
            if ($error->transactionId === $transaction->id) {
                $idToErrorForTransaction[$error->id] = $error;
            }
        }

        return $idToErrorForTransaction;
    }

    /**
     * @param string $parentId
     *
     * @return iterable<SpanDto>
     */
    public function findChildSpans(string $parentId): iterable
    {
        foreach ($this->idToSpan as $span) {
            if ($span->parentId === $parentId) {
                yield $span;
            }
        }
    }

    /**
     * @param string $parentId
     *
     * @return iterable<ErrorDto>
     */
    public function findChildErrors(string $parentId): iterable
    {
        foreach ($this->idToError as $error) {
            if ($error->parentId === $parentId) {
                yield $error;
            }
        }
    }

    /**
     * @param array<string, TransactionDto> $idToTransaction
     * @param array<string, SpanDto>        $idToSpan
     * @param string                        $id
     *
     * @return ?ExecutionSegmentDto
     */
    public static function executionSegmentByIdOrNullEx(
        array $idToTransaction,
        array $idToSpan,
        string $id
    ): ?ExecutionSegmentDto {
        if (($span = ArrayUtil::getValueIfKeyExistsElse($id, $idToSpan, null)) !== null) {
            return $span;
        }
        return ArrayUtil::getValueIfKeyExistsElse($id, $idToTransaction, null);
    }

    /**
     * @param array<string, TransactionDto> $idToTransaction
     * @param array<string, SpanDto>        $idToSpan
     * @param string                         $id
     *
     * @return ExecutionSegmentDto
     */
    public static function executionSegmentByIdEx(
        array $idToTransaction,
        array $idToSpan,
        string $id
    ): ExecutionSegmentDto {
        $result = self::executionSegmentByIdOrNullEx($idToTransaction, $idToSpan, $id);
        TestCaseBase::assertNotNull($result);
        return $result;
    }

    public function executionSegmentByIdOrNull(string $id): ?ExecutionSegmentDto
    {
        return self::executionSegmentByIdOrNullEx($this->idToTransaction, $this->idToSpan, $id);
    }

    public function executionSegmentById(string $id): ExecutionSegmentDto
    {
        return self::executionSegmentByIdEx($this->idToTransaction, $this->idToSpan, $id);
    }

    public function getApmDataCountForKind(ApmDataKind $apmDataKind): int
    {
        switch ($apmDataKind) {
            case ApmDataKind::error():
                return count($this->idToError);
            case ApmDataKind::metadata():
                return count($this->metadatas);
            case ApmDataKind::metricSet():
                return count($this->metricSets);
            case ApmDataKind::span():
                return count($this->idToSpan);
            case ApmDataKind::transaction():
                return count($this->idToTransaction);
            default:
                TestCase::fail('Unknown $apmDataKind: ' . $apmDataKind->asString());
        }
    }
}
