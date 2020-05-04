<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\Util;

use Closure;
use Elastic\Apm\Impl\EventSinkInterface;
use Elastic\Apm\Impl\Metadata;
use Elastic\Apm\Impl\MetadataInterface;
use Elastic\Apm\Impl\ServerComm\SerializationUtil;
use Elastic\Apm\Impl\SpanData;
use Elastic\Apm\Impl\TransactionData;
use Elastic\Apm\SpanDataInterface;
use Elastic\Apm\Tests\Deserialization\MetadataDeserializer;
use Elastic\Apm\Tests\Deserialization\SerializationTestUtil;
use Elastic\Apm\Tests\Deserialization\ServerApiSchemaValidator;
use Elastic\Apm\Tests\Deserialization\SpanDataDeserializer;
use Elastic\Apm\Tests\Deserialization\TransactionDataDeserializer;
use Elastic\Apm\TransactionDataInterface;

class MockEventSink implements EventSinkInterface
{
    /** @var MetadataInterface[] */
    private $metadata = [];

    /** @var TransactionDataInterface[] */
    private $transactions = [];

    /** @var SpanDataInterface[] */
    private $spans = [];

    /**
     * @param object $data
     * @param Closure(object): void $assertValid
     * @param Closure(object): string $serialize
     * @param Closure(string): void $validateAgainstSchema
     * @param Closure(array<string, mixed>): object $deserialize
     * @param Closure(object, object): void $assertEquals
     *
     * @template        T of object
     * @phpstan-param   T $data
     * @phpstan-param   Closure(T): void $assertValid
     * @phpstan-param   Closure(T): string $serialize
     * @phpstan-param   Closure(string): void $validateAgainstSchema
     * @phpstan-param   Closure(array<string, mixed>): T $deserialize
     * @phpstan-param   Closure(T, T): void $assertEquals
     * @phpstan-return  T
     */
    private function validateAndPassThroughSerialization(
        object $data,
        Closure $assertValid,
        Closure $serialize,
        Closure $validateAgainstSchema,
        Closure $deserialize,
        Closure $assertEquals
    ): object {
        $assertValid($data);
        $serializedData = $serialize($data);
        $validateAgainstSchema($serializedData);
        $deserializedJson = SerializationTestUtil::deserializeJson($serializedData, /* asAssocArray */ true);
        $deserializedData = $deserialize($deserializedJson);
        $assertValid($deserializedData);
        $assertEquals($data, $deserializedData);
        return $deserializedData;
    }

    /** @inheritDoc */
    public function setMetadata(MetadataInterface $metadata): void
    {
        $this->metadata[] = self::validateAndPassThroughSerialization(
            $metadata,
            /* assertValid: */
            function (MetadataInterface $data): void {
                ValidationUtil::assertValidMetadata($data);
            },
            /* serialize: */
            function (MetadataInterface $data): string {
                return SerializationUtil::serializeMetadata($data);
            },
            /* validateAgainstSchema: */
            function (string $serializedData): void {
                ServerApiSchemaValidator::validateMetadata($serializedData);
            },
            /* deserialize: */
            function ($deserializedRawData): Metadata {
                return MetadataDeserializer::deserialize($deserializedRawData);
            },
            /* assertEquals: */
            function ($data, $deserializedData): void {
                ValidationUtil::assertThat(Metadata::convertToData($data) == $deserializedData);
            }
        );
    }

    public function clearMetadata(): void
    {
        $this->metadata = [];
    }

    public function consumeTransactionData(TransactionDataInterface $transactionData): void
    {
        $this->transactions[] = self::validateAndPassThroughSerialization(
            $transactionData,
            /* assertValid: */
            function (TransactionDataInterface $data): void {
                ValidationUtil::assertValidTransactionData($data);
            },
            /* serialize: */
            function (TransactionDataInterface $data): string {
                return SerializationUtil::serializeTransaction($data);
            },
            /* validateAgainstSchema: */
            function (string $serializedData): void {
                ServerApiSchemaValidator::validateTransactionData($serializedData);
            },
            /* deserialize: */
            function ($deserializedRawData): TransactionData {
                return TransactionDataDeserializer::deserialize($deserializedRawData);
            },
            /* assertEquals: */
            function ($data, $deserializedData): void {
                ValidationUtil::assertThat(TransactionData::convertToData($data) == $deserializedData);
            }
        );
    }

    /** @return TransactionDataInterface[] */
    public function getTransactions(): array
    {
        return $this->transactions;
    }

    public function clearTransactions(): void
    {
        $this->transactions = [];
    }

    public function consumeSpanData(SpanDataInterface $spanData): void
    {
        $this->spans[] = self::validateAndPassThroughSerialization(
            $spanData,
            /* assertValid: */
            function (SpanDataInterface $data): void {
                ValidationUtil::assertValidSpanData($data);
            },
            /* serialize: */
            function (SpanDataInterface $data): string {
                return SerializationUtil::serializeSpan($data);
            },
            /* validateAgainstSchema: */
            function (string $serializedData): void {
                ServerApiSchemaValidator::validateSpanData($serializedData);
            },
            /* deserialize: */
            function ($deserializedRawData): SpanData {
                return SpanDataDeserializer::deserialize($deserializedRawData);
            },
            /* assertEquals: */
            function ($data, $deserializedData): void {
                ValidationUtil::assertThat(SpanData::convertToData($data) == $deserializedData);
            }
        );
    }

    /** @return SpanDataInterface[] */
    public function getSpans(): array
    {
        return $this->spans;
    }

    public function clearSpans(): void
    {
        $this->spans = [];
    }

    /**
     * @param string $name
     *
     * @return SpanDataInterface
     * @throws NotFoundException
     */
    public function getSpanByName(string $name): SpanDataInterface
    {
        $index = ArrayUtil::findIndexByPredicate(
            $this->spans,
            function (SpanDataInterface $s) use ($name): bool {
                return $s->getName() === $name;
            }
        );
        if ($index === -1) {
            throw new NotFoundException("Span with the name `$name' not found");
        }
        return $this->spans[$index];
    }

    /**
     * @param TransactionDataInterface $transaction
     *
     * @return array<SpanDataInterface>
     */
    public function getSpansForTransaction(TransactionDataInterface $transaction): array
    {
        return array_filter(
            $this->spans,
            function (SpanDataInterface $span) use ($transaction): bool {
                return $span->getTransactionId() === $transaction->getId();
            }
        );
    }
}
