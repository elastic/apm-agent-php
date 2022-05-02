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

use Elastic\Apm\Impl\ErrorData;
use Elastic\Apm\Impl\ExecutionSegmentData;
use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableTrait;
use Elastic\Apm\Impl\Metadata;
use Elastic\Apm\Impl\MetricSetData;
use Elastic\Apm\Impl\SpanData;
use Elastic\Apm\Impl\TransactionData;
use Elastic\Apm\Impl\Util\ArrayUtil;
use ElasticApmTests\ComponentTests\Util\ApmDataKind;
use ElasticApmTests\UnitTests\Util\NotFoundException;
use PHPUnit\Framework\TestCase;

class DataFromAgent implements LoggableInterface
{
    use LoggableTrait;

    /** @var Metadata[] */
    public $metadatas = [];

    /** @var array<string, TransactionData> */
    public $idToTransaction = [];

    /** @var array<string, SpanData> */
    public $idToSpan = [];

    /** @var array<string, ErrorData> */
    public $idToError = [];

    /** @var MetricSetData[] */
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
        TestCase::assertCount(1, $events);
        return ArrayUtilForTests::getFirstValue($events);
    }

    /**
     * @return TransactionData
     */
    public function singleTransaction(): TransactionData
    {
        return self::singleEvent($this->idToTransaction);
    }

    /**
     * @return SpanData
     */
    public function singleSpan(): SpanData
    {
        return self::singleEvent($this->idToSpan);
    }

    public function singleError(): ErrorData
    {
        return self::singleEvent($this->idToError);
    }

    /**
     * @param string $name
     *
     * @return SpanData
     * @throws NotFoundException
     */
    public function spanByName(string $name): SpanData
    {
        foreach ($this->idToSpan as $span) {
            if ($span->name === $name) {
                return $span;
            }
        }
        throw new NotFoundException("Span with the name `$name' not found");
    }

    /**
     * @param TransactionData $transaction
     *
     * @return array<string, SpanData>
     */
    public function spansForTransaction(TransactionData $transaction): array
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
     * @param TransactionData $transaction
     *
     * @return array<string, ErrorData>
     */
    public function errorsForTransaction(TransactionData $transaction): array
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
     * @return iterable<SpanData>
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
     * @return iterable<ErrorData>
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
     * @param array<string, TransactionData> $idToTransaction
     * @param array<string, SpanData>        $idToSpan
     * @param string                         $id
     *
     * @return ?ExecutionSegmentData
     */
    public static function executionSegmentByIdOrNullEx(
        array $idToTransaction,
        array $idToSpan,
        string $id
    ): ?ExecutionSegmentData {
        if (($span = ArrayUtil::getValueIfKeyExistsElse($id, $idToSpan, null)) !== null) {
            return $span;
        }
        return ArrayUtil::getValueIfKeyExistsElse($id, $idToTransaction, null);
    }

    /**
     * @param array<string, TransactionData> $idToTransaction
     * @param array<string, SpanData>        $idToSpan
     * @param string                         $id
     *
     * @return ExecutionSegmentData
     */
    public static function executionSegmentByIdEx(
        array $idToTransaction,
        array $idToSpan,
        string $id
    ): ExecutionSegmentData {
        $result = self::executionSegmentByIdOrNullEx($idToTransaction, $idToSpan, $id);
        TestCaseBase::assertNotNull($result);
        return $result;
    }

    public function executionSegmentByIdOrNull(string $id): ?ExecutionSegmentData
    {
        return self::executionSegmentByIdOrNullEx($this->idToTransaction, $this->idToSpan, $id);
    }

    public function executionSegmentById(string $id): ExecutionSegmentData
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
