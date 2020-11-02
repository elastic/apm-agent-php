<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\UnitTests\Util;

use Closure;
use Elastic\Apm\Impl\BackendComm\SerializationUtil;
use Elastic\Apm\Impl\EventSinkInterface;
use Elastic\Apm\Impl\Metadata;
use Elastic\Apm\Impl\MetadataInterface;
use Elastic\Apm\Impl\SpanData;
use Elastic\Apm\Impl\TransactionData;
use Elastic\Apm\SpanDataInterface;
use Elastic\Apm\Tests\TestsSharedCode\EventsFromAgent;
use Elastic\Apm\Tests\Util\Deserialization\SerializedEventSinkTrait;
use Elastic\Apm\Tests\Util\ValidationUtil;
use Elastic\Apm\TransactionDataInterface;
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

    public function setMetadata(MetadataInterface $metadata): void
    {
        $this->eventsFromAgent->metadata[] = self::passThroughSerialization(
            $metadata,
            /* assertValid: */
            function (MetadataInterface $data): void {
                ValidationUtil::assertValidMetadata($data);
                self::additionalMetadataValidation($data);
            },
            /* serialize: */
            function (MetadataInterface $data): string {
                return SerializationUtil::serializeMetadata($data);
            },
            /* validateAndDeserialize: */
            function (string $serializedMetadata): MetadataInterface {
                return $this->validateAndDeserializeMetadata($serializedMetadata);
            },
            /* assertEquals: */
            function ($data, $deserializedData): void {
                ValidationUtil::assertThat(Metadata::convertToData($data) == $deserializedData);
            }
        );
    }

    public function consume(array $spans, ?TransactionDataInterface $transaction): void
    {
        foreach ($spans as $span) {
            $this->consumeSpanData($span);
        }

        if (!is_null($transaction)) {
            $this->consumeTransactionData($transaction);
        }
    }

    private function consumeTransactionData(TransactionDataInterface $transaction): void
    {
        /** @var TransactionDataInterface $newTransaction */
        $newTransaction = self::passThroughSerialization(
            $transaction,
            /* assertValid: */
            function (TransactionDataInterface $data): void {
                ValidationUtil::assertValidTransactionData($data);
            },
            /* serialize: */
            function (TransactionDataInterface $data): string {
                return SerializationUtil::serializeTransaction($data);
            },
            /* validateAndDeserialize: */
            function (string $serializedTransactionData): TransactionDataInterface {
                return $this->validateAndDeserializeTransactionData($serializedTransactionData);
            },
            /* assertEquals: */
            function ($data, $deserializedData): void {
                TestCase::assertEquals(TransactionData::convertToData($data), $deserializedData);
            }
        );
        TestCase::assertArrayNotHasKey($newTransaction->getId(), $this->eventsFromAgent->idToTransaction);
        $this->eventsFromAgent->idToTransaction[$newTransaction->getId()] = $newTransaction;
    }

    private function consumeSpanData(SpanDataInterface $spanData): void
    {
        /** @var SpanDataInterface $newSpan */
        $newSpan = self::passThroughSerialization(
            $spanData,
            /* assertValid: */
            function (SpanDataInterface $data): void {
                ValidationUtil::assertValidSpanData($data);
            },
            /* serialize: */
            function (SpanDataInterface $data): string {
                return SerializationUtil::serializeSpan($data);
            },
            /* validateAndDeserialize: */
            function (string $serializedSpanData): SpanDataInterface {
                return $this->validateAndDeserializeSpanData($serializedSpanData);
            },
            /* assertEquals: */
            function ($data, $deserializedData): void {
                ValidationUtil::assertThat(SpanData::convertToData($data) == $deserializedData);
            }
        );
        TestCase::assertArrayNotHasKey($newSpan->getId(), $this->eventsFromAgent->idToSpan);
        $this->eventsFromAgent->idToSpan[$newSpan->getId()] = $newSpan;
    }

    public static function additionalMetadataValidation(MetadataInterface $metadata): void
    {
        TestCase::assertSame(getmypid(), $metadata->process()->pid());
        TestCase::assertNotNull($metadata->service()->language());
        TestCase::assertSame(PHP_VERSION, $metadata->service()->language()->version());
    }

    /**
     * @return array<string, TransactionDataInterface>
     */
    public function idToTransaction(): array
    {
        return $this->eventsFromAgent->idToTransaction;
    }

    /**
     * @return TransactionDataInterface
     */
    public function singleTransaction(): TransactionDataInterface
    {
        return $this->eventsFromAgent->singleTransaction();
    }

    /**
     * @return array<string, SpanDataInterface>
     */
    public function idToSpan(): array
    {
        return $this->eventsFromAgent->idToSpan;
    }

    /**
     * @return SpanDataInterface
     */
    public function singleSpan(): SpanDataInterface
    {
        return $this->eventsFromAgent->singleSpan();
    }

    /**
     * @param string $name
     *
     * @return SpanDataInterface
     * @throws NotFoundException
     */
    public function spanByName(string $name): SpanDataInterface
    {
        return $this->eventsFromAgent->spanByName($name);
    }

    /**
     * @param TransactionDataInterface $transaction
     *
     * @return array<string, SpanDataInterface>
     */
    public function spansForTransaction(TransactionDataInterface $transaction): array
    {
        return $this->eventsFromAgent->spansForTransaction($transaction);
    }
}
