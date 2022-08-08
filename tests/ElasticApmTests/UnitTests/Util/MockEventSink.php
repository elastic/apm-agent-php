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

use Closure;
use Elastic\Apm\Impl\BackendComm\SerializationUtil;
use Elastic\Apm\Impl\BreakdownMetrics\PerTransaction as BreakdownMetricsPerTransaction;
use Elastic\Apm\Impl\ErrorData;
use Elastic\Apm\Impl\EventSinkInterface;
use Elastic\Apm\Impl\Metadata;
use Elastic\Apm\Impl\MetricSetData;
use Elastic\Apm\Impl\SpanData;
use Elastic\Apm\Impl\TransactionData;
use ElasticApmTests\Util\DataFromAgent;
use ElasticApmTests\Util\Deserialization\SerializedEventSinkTrait;
use ElasticApmTests\Util\ErrorDataValidator;
use ElasticApmTests\Util\MetadataValidator;
use ElasticApmTests\Util\MetricSetDataValidator;
use ElasticApmTests\Util\SpanDataValidator;
use ElasticApmTests\Util\TransactionDataValidator;
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

    /**
     * @param object                        $data
     * @param Closure(object): void         $assertValid
     * @param Closure(object): string       $serialize
     * @param Closure(string): object       $validateAndDeserialize
     * @param Closure(object, object): void $assertEquals
     *
     * @return object
     *
     * @template        T of object
     *
     * @phpstan-param   T                   $data
     * @phpstan-param   Closure(T): void    $assertValid
     * @phpstan-param   Closure(T): string  $serialize
     * @phpstan-param   Closure(string): T  $validateAndDeserialize
     * @phpstan-param   Closure(T, T): void $assertEquals
     *
     * @phpstan-return  T
     */
    private function passThroughSerialization(
        object $data,
        Closure $assertValid,
        Closure $serialize,
        Closure $validateAndDeserialize,
        Closure $assertEquals
    ): object {
        $assertValid($data);
        $serializedData = $serialize($data);
        $deserializedData = $validateAndDeserialize($serializedData);
        $assertValid($deserializedData);
        $assertEquals($data, $deserializedData);
        return $deserializedData;
    }

    /** @inheritDoc */
    public function consume(
        Metadata $metadata,
        array $spansData,
        array $errorsData,
        ?BreakdownMetricsPerTransaction $breakdownMetricsPerTransaction,
        ?TransactionData $transactionData
    ): void {
        $this->consumeMetadata($metadata);

        foreach ($spansData as $span) {
            $this->consumeSpanData($span);
        }

        foreach ($errorsData as $error) {
            $this->consumeErrorData($error);
        }

        if ($breakdownMetricsPerTransaction !== null) {
            $breakdownMetricsPerTransaction->forEachMetricSet(
                function (MetricSetData $metricSetData) {
                    $this->consumeMetricSetData($metricSetData);
                }
            );
        }

        if ($transactionData !== null) {
            $this->consumeTransactionData($transactionData);
        }
    }

    private function consumeMetadata(Metadata $metadata): void
    {
        $this->dataFromAgent->metadatas[] = self::passThroughSerialization(
            $metadata,
            /* assertValid: */
            function (Metadata $data): void {
                MetadataValidator::validate($data);
                self::additionalMetadataValidation($data);
            },
            /* serialize: */
            function (Metadata $data): string {
                return SerializationUtil::serializeAsJson($data);
            },
            /* validateAndDeserialize: */
            function (string $serializedMetadata): Metadata {
                return $this->validateAndDeserializeMetadata($serializedMetadata);
            },
            /* assertEquals: */
            function ($data, $deserializedData): void {
                TestCase::assertEquals($data, $deserializedData);
            }
        );
    }

    private function consumeTransactionData(TransactionData $transaction): void
    {
        /** @var TransactionData $newTransaction */
        $newTransaction = self::passThroughSerialization(
            $transaction,
            /* assertValid: */
            function (TransactionData $data): void {
                TransactionDataValidator::validate($data);
            },
            /* serialize: */
            function (TransactionData $data): string {
                return SerializationUtil::serializeAsJson($data);
            },
            /* validateAndDeserialize: */
            function (string $serializedTransactionData): TransactionData {
                return $this->validateAndDeserializeTransactionData($serializedTransactionData);
            },
            /* assertEquals: */
            function ($data, $deserializedData): void {
                TestCase::assertEquals($data, $deserializedData);
            }
        );
        TestCase::assertNull($this->dataFromAgent->executionSegmentByIdOrNull($newTransaction->id));
        $this->dataFromAgent->idToTransaction[$newTransaction->id] = $newTransaction;
    }

    private function consumeSpanData(SpanData $spanData): void
    {
        /** @var SpanData $newSpan */
        $newSpan = self::passThroughSerialization(
            $spanData,
            /* assertValid: */
            function (SpanData $data): void {
                SpanDataValidator::validate($data);
            },
            /* serialize: */
            function (SpanData $data): string {
                return SerializationUtil::serializeAsJson($data);
            },
            /* validateAndDeserialize: */
            function (string $serializedSpanData): SpanData {
                return $this->validateAndDeserializeSpanData($serializedSpanData);
            },
            /* assertEquals: */
            function ($data, $deserializedData): void {
                TestCase::assertEquals($data, $deserializedData);
            }
        );
        TestCase::assertNull($this->dataFromAgent->executionSegmentByIdOrNull($newSpan->id));
        $this->dataFromAgent->idToSpan[$newSpan->id] = $newSpan;
    }

    private function consumeErrorData(ErrorData $errorData): void
    {
        /** @var ErrorData $newError */
        $newError = self::passThroughSerialization(
            $errorData,
            /* assertValid: */
            function (ErrorData $data): void {
                ErrorDataValidator::validate($data);
            },
            /* serialize: */
            function (ErrorData $data): string {
                return SerializationUtil::serializeAsJson($data);
            },
            /* validateAndDeserialize: */
            function (string $serializedErrorData): ErrorData {
                return $this->validateAndDeserializeErrorData($serializedErrorData);
            },
            /* assertEquals: */
            function ($data, $deserializedData): void {
                TestCase::assertEquals($data, $deserializedData);
            }
        );
        TestCase::assertArrayNotHasKey($newError->id, $this->dataFromAgent->idToError);
        $this->dataFromAgent->idToError[$newError->id] = $newError;
    }

    private function consumeMetricSetData(MetricSetData $metricSetData): void
    {
        /** @var MetricSetData $newMetricSetData */
        $newMetricSetData = self::passThroughSerialization(
            $metricSetData,
            /* assertValid: */
            function (MetricSetData $data): void {
                MetricSetDataValidator::validate($data);
            },
            /* serialize: */
            function (MetricSetData $data): string {
                return SerializationUtil::serializeAsJson($data);
            },
            /* validateAndDeserialize: */
            function (string $serializedErrorData): MetricSetData {
                return $this->validateAndDeserializeMetricSetData($serializedErrorData);
            },
            /* assertEquals: */
            function ($data, $deserializedData): void {
                TestCase::assertEquals($data, $deserializedData);
            }
        );

        $this->dataFromAgent->metricSets[] = $newMetricSetData;
    }

    public static function additionalMetadataValidation(Metadata $metadata): void
    {
        TestCase::assertNotNull($metadata->process);
        TestCase::assertSame(getmypid(), $metadata->process->pid);
        TestCase::assertNotNull($metadata->service->language);
        TestCase::assertSame(PHP_VERSION, $metadata->service->language->version);
    }

    /**
     * @return array<string, TransactionData>
     */
    public function idToTransaction(): array
    {
        return $this->dataFromAgent->idToTransaction;
    }

    /**
     * @return TransactionData
     */
    public function singleTransaction(): TransactionData
    {
        return $this->dataFromAgent->singleTransaction();
    }

    /**
     * @return array<string, SpanData>
     */
    public function idToSpan(): array
    {
        return $this->dataFromAgent->idToSpan;
    }

    /**
     * @return SpanData
     */
    public function singleSpan(): SpanData
    {
        return $this->dataFromAgent->singleSpan();
    }

    /**
     * @param string $name
     *
     * @return SpanData
     * @throws NotFoundException
     */
    public function spanByName(string $name): SpanData
    {
        return $this->dataFromAgent->spanByName($name);
    }

    /**
     * @param TransactionData $transaction
     *
     * @return array<string, SpanData>
     */
    public function spansForTransaction(TransactionData $transaction): array
    {
        return $this->dataFromAgent->spansForTransaction($transaction);
    }
}
