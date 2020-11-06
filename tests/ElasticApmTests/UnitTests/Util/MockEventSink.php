<?php

declare(strict_types=1);

namespace ElasticApmTests\UnitTests\Util;

use Closure;
use Elastic\Apm\Impl\BackendComm\SerializationUtil;
use Elastic\Apm\Impl\EventSinkInterface;
use Elastic\Apm\Impl\Metadata;
use Elastic\Apm\Impl\SpanData;
use Elastic\Apm\Impl\TransactionData;
use ElasticApmTests\TestsSharedCode\EventsFromAgent;
use ElasticApmTests\Util\Deserialization\SerializedEventSinkTrait;
use ElasticApmTests\Util\ValidationUtil;
use PHPUnit\Framework\TestCase;

class MockEventSink implements EventSinkInterface
{
    use SerializedEventSinkTrait;

    /** @var EventsFromAgent */
    public $eventsFromAgent;

    public function __construct()
    {
        $this->eventsFromAgent = new EventsFromAgent();
    }

    public function clear(): void
    {
        $this->eventsFromAgent->clear();
    }

    /**
     * @param object $data
     * @param Closure(object): void $assertValid
     * @param Closure(object): string $serialize
     * @param Closure(string): object $validateAndDeserialize
     * @param Closure(object, object): void $assertEquals
     *
     * @return object
     *
     * @template        T of object
     *
     * @phpstan-param   T $data
     * @phpstan-param   Closure(T): void $assertValid
     * @phpstan-param   Closure(T): string $serialize
     * @phpstan-param   Closure(string): T $validateAndDeserialize
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

    public function setMetadata(Metadata $metadata): void
    {
        $this->eventsFromAgent->metadata[] = self::passThroughSerialization(
            $metadata,
            /* assertValid: */
            function (Metadata $data): void {
                ValidationUtil::assertValidMetadata($data);
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

    public function consume(array $spansData, ?TransactionData $transactionData): void
    {
        foreach ($spansData as $span) {
            $this->consumeSpanData($span);
        }

        if (!is_null($transactionData)) {
            $this->consumeTransactionData($transactionData);
        }
    }

    private function consumeTransactionData(TransactionData $transaction): void
    {
        /** @var TransactionData $newTransaction */
        $newTransaction = self::passThroughSerialization(
            $transaction,
            /* assertValid: */
            function (TransactionData $data): void {
                ValidationUtil::assertValidTransactionData($data);
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
        TestCase::assertArrayNotHasKey($newTransaction->id, $this->eventsFromAgent->idToTransaction);
        $this->eventsFromAgent->idToTransaction[$newTransaction->id] = $newTransaction;
    }

    private function consumeSpanData(SpanData $spanData): void
    {
        /** @var SpanData $newSpan */
        $newSpan = self::passThroughSerialization(
            $spanData,
            /* assertValid: */
            function (SpanData $data): void {
                ValidationUtil::assertValidSpanData($data);
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
        TestCase::assertArrayNotHasKey($newSpan->id, $this->eventsFromAgent->idToSpan);
        $this->eventsFromAgent->idToSpan[$newSpan->id] = $newSpan;
    }

    public static function additionalMetadataValidation(Metadata $metadata): void
    {
        TestCase::assertSame(getmypid(), $metadata->process->pid);
        TestCase::assertNotNull($metadata->service->language);
        TestCase::assertSame(PHP_VERSION, $metadata->service->language->version);
    }

    /**
     * @return array<string, TransactionData>
     */
    public function idToTransaction(): array
    {
        return $this->eventsFromAgent->idToTransaction;
    }

    /**
     * @return TransactionData
     */
    public function singleTransaction(): TransactionData
    {
        return $this->eventsFromAgent->singleTransaction();
    }

    /**
     * @return array<string, SpanData>
     */
    public function idToSpan(): array
    {
        return $this->eventsFromAgent->idToSpan;
    }

    /**
     * @return SpanData
     */
    public function singleSpan(): SpanData
    {
        return $this->eventsFromAgent->singleSpan();
    }

    /**
     * @param string $name
     *
     * @return SpanData
     * @throws NotFoundException
     */
    public function spanByName(string $name): SpanData
    {
        return $this->eventsFromAgent->spanByName($name);
    }

    /**
     * @param TransactionData $transaction
     *
     * @return array<string, SpanData>
     */
    public function spansForTransaction(TransactionData $transaction): array
    {
        return $this->eventsFromAgent->spansForTransaction($transaction);
    }
}
