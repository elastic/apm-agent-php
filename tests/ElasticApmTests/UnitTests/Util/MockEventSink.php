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

namespace ElasticApmTests\UnitTests\Util;

use Elastic\Apm\Impl\BackendComm\SerializationUtil;
use Elastic\Apm\Impl\BreakdownMetrics\PerTransaction as BreakdownMetricsPerTransaction;
use Elastic\Apm\Impl\Error;
use Elastic\Apm\Impl\EventSinkInterface;
use Elastic\Apm\Impl\Metadata;
use Elastic\Apm\Impl\MetricSet;
use Elastic\Apm\Impl\Span;
use Elastic\Apm\Impl\SpanToSendInterface;
use Elastic\Apm\Impl\Transaction;
use ElasticApmTests\Util\ArrayUtilForTests;
use ElasticApmTests\Util\DataFromAgent;
use ElasticApmTests\Util\Deserialization\SerializedEventSinkTrait;
use ElasticApmTests\Util\MetadataValidator;
use ElasticApmTests\Util\MetricSetValidator;
use ElasticApmTests\Util\SpanDto;
use ElasticApmTests\Util\TransactionDto;
use PHPUnit\Framework\TestCase;

final class MockEventSink implements EventSinkInterface
{
    use SerializedEventSinkTrait;

    /** @var DataFromAgent */
    public $dataFromAgent;

    public function __construct()
    {
        $this->dataFromAgent = new DataFromAgent();
    }

    public function clear(): void
    {
        $this->dataFromAgent = new DataFromAgent();
    }

    /** @inheritDoc */
    public function consume(
        Metadata $metadata,
        array $spans,
        array $errors,
        ?BreakdownMetricsPerTransaction $breakdownMetricsPerTransaction,
        ?Transaction $transaction
    ): void {
        $this->consumeMetadata($metadata);

        foreach ($spans as $span) {
            $this->consumeSpan($span);
        }

        foreach ($errors as $error) {
            $this->consumeError($error);
        }

        if ($breakdownMetricsPerTransaction !== null) {
            $breakdownMetricsPerTransaction->forEachMetricSet(
                function (MetricSet $metricSetData) {
                    $this->consumeMetricSet($metricSetData);
                }
            );
        }

        if ($transaction !== null) {
            $this->consumeTransaction($transaction);
        }
    }

    private static function assertValidMetadata(Metadata $metadata): void
    {
        MetadataValidator::assertValid($metadata);

        TestCase::assertNotNull($metadata->process);
        TestCase::assertSame(getmypid(), $metadata->process->pid);
        TestCase::assertNotNull($metadata->service->language);
        TestCase::assertSame(PHP_VERSION, $metadata->service->language->version);
    }

    private function consumeMetadata(Metadata $original): void
    {
        self::assertValidMetadata($original);

        $serialized = SerializationUtil::serializeAsJson($original);

        $deserialized = $this->validateAndDeserializeMetadata($serialized);

        self::assertValidMetadata($deserialized);
        TestCase::assertEquals($original, $deserialized);
    }

    private function consumeTransaction(Transaction $original): void
    {
        TestCase::assertTrue($original->hasEnded());

        $serialized = SerializationUtil::serializeAsJson($original);

        $deserialized = $this->validateAndDeserializeTransaction($serialized);
        $deserialized->assertValid();
        $deserialized->assertEquals($original);

        TestCase::assertNull($this->dataFromAgent->executionSegmentByIdOrNull($deserialized->id));
        ArrayUtilForTests::addUnique($deserialized->id, $deserialized, /* ref */ $this->dataFromAgent->idToTransaction);
    }

    private function consumeSpan(SpanToSendInterface $original): void
    {
        if ($original instanceof Span) {
            TestCase::assertTrue($original->hasEnded());
        }
        $serialized = SerializationUtil::serializeAsJson($original);

        $deserialized = $this->validateAndDeserializeSpan($serialized);
        $deserialized->assertValid();
        $deserialized->assertEquals($original);

        TestCase::assertNull($this->dataFromAgent->executionSegmentByIdOrNull($deserialized->id));
        ArrayUtilForTests::addUnique($deserialized->id, $deserialized, /* ref */ $this->dataFromAgent->idToSpan);
    }

    private function consumeError(Error $original): void
    {
        $serialized = SerializationUtil::serializeAsJson($original);

        $deserialized = $this->validateAndDeserializeError($serialized);
        $deserialized->assertValid();
        $deserialized->assertEquals($original);

        ArrayUtilForTests::addUnique($deserialized->id, $deserialized, /* ref */ $this->dataFromAgent->idToError);
    }

    private function consumeMetricSet(MetricSet $original): void
    {
        MetricSetValidator::assertValid($original);

        $serialized = SerializationUtil::serializeAsJson($original);

        $deserialized = $this->validateAndDeserializeMetricSet($serialized);
        MetricSetValidator::assertValid($deserialized);
        TestCase::assertEquals($original, $deserialized);

        $this->dataFromAgent->metricSets[] = $deserialized;
    }

    /**
     * @return array<string, TransactionDto>
     */
    public function idToTransaction(): array
    {
        return $this->dataFromAgent->idToTransaction;
    }

    /**
     * @return TransactionDto
     */
    public function singleTransaction(): TransactionDto
    {
        return $this->dataFromAgent->singleTransaction();
    }

    /**
     * @return array<string, SpanDto>
     */
    public function idToSpan(): array
    {
        return $this->dataFromAgent->idToSpan;
    }

    /**
     * @return SpanDto
     */
    public function singleSpan(): SpanDto
    {
        return $this->dataFromAgent->singleSpan();
    }

    /**
     * @param string $name
     *
     * @return SpanDto[]
     */
    public function findSpansByName(string $name): array
    {
        return $this->dataFromAgent->findSpansByName($name);
    }

    /**
     * @param string $name
     *
     * @return SpanDto
     */
    public function singleSpanByName(string $name): SpanDto
    {
        return $this->dataFromAgent->singleSpanByName($name);
    }

    /**
     * @param TransactionDto $transaction
     *
     * @return array<string, SpanDto>
     */
    public function spansForTransaction(TransactionDto $transaction): array
    {
        return $this->dataFromAgent->spansForTransaction($transaction);
    }
}
