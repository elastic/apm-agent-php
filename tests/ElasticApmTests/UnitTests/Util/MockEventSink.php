<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\UnitTests\Util;

use Closure;
use Elastic\Apm\Impl\EventSinkInterface;
use Elastic\Apm\Impl\Metadata;
use Elastic\Apm\Impl\MetadataInterface;
use Elastic\Apm\Impl\ServerComm\SerializationUtil;
use Elastic\Apm\Impl\SpanData;
use Elastic\Apm\Impl\TransactionData;
use Elastic\Apm\SpanDataInterface;
use Elastic\Apm\Tests\Util\Deserialization\SerializedEventSinkTrait;
use Elastic\Apm\Tests\Util\ValidationUtil;
use Elastic\Apm\TransactionDataInterface;
use PHPUnit\Framework\TestCase;

class MockEventSink implements EventSinkInterface
{
    use SerializedEventSinkTrait;

    /** @var MetadataInterface[] */
    private $metadata = [];

    /** @var array<string, TransactionDataInterface> */
    private $idToTransaction = [];

    /** @var array<string, SpanDataInterface> */
    private $idToSpan = [];

    /**
     * @param object $data
     * @param Closure(object): void $assertValid
     * @param Closure(object): string $serialize
     * @param Closure(string): object $validateAndDeserialize
     * @param Closure(object, object): void $assertEquals
     *
     * @template        T of object
     * @phpstan-param   T $data
     * @phpstan-param   Closure(T): void $assertValid
     * @phpstan-param   Closure(T): string $serialize
     * @phpstan-param   Closure(string): T $validateAndDeserialize
     * @phpstan-param   Closure(T, T): void $assertEquals
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
    public function setMetadata(MetadataInterface $metadata): void
    {
        $this->metadata[] = self::passThroughSerialization(
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
                return self::validateAndDeserializeMetadata($serializedMetadata);
            },
            /* assertEquals: */
            function ($data, $deserializedData): void {
                ValidationUtil::assertThat(Metadata::convertToData($data) == $deserializedData);
            }
        );
    }

    public static function additionalMetadataValidation(MetadataInterface $metadata): void
    {
        TestCase::assertSame(getmypid(), $metadata->process()->pid());
        TestCase::assertNotNull($metadata->service()->language());
        TestCase::assertSame(PHP_VERSION, $metadata->service()->language()->version());
    }

    public function consumeTransactionData(TransactionDataInterface $transactionData): void
    {
        /** @var TransactionDataInterface $newTransaction */
        $newTransaction = self::passThroughSerialization(
            $transactionData,
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
                return self::validateAndDeserializeTransactionData($serializedTransactionData);
            },
            /* assertEquals: */
            function ($data, $deserializedData): void {
                ValidationUtil::assertThat(TransactionData::convertToData($data) == $deserializedData);
            }
        );
        TestCase::assertArrayNotHasKey($newTransaction->getId(), $this->idToTransaction);
        $this->idToTransaction[$newTransaction->getId()] = $newTransaction;
    }

    /**
     * @return array<string, TransactionDataInterface>
     */
    public function getIdToTransaction(): array
    {
        return $this->idToTransaction;
    }

    /**
     * @return TransactionDataInterface
     */
    public function getSingleTransaction(): TransactionDataInterface
    {
        TestCase::assertCount(1, $this->idToTransaction);
        return $this->idToTransaction[array_key_first($this->idToTransaction)];
    }

    public function consumeSpanData(SpanDataInterface $spanData): void
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
                return self::validateAndDeserializeSpanData($serializedSpanData);
            },
            /* assertEquals: */
            function ($data, $deserializedData): void {
                ValidationUtil::assertThat(SpanData::convertToData($data) == $deserializedData);
            }
        );
        TestCase::assertArrayNotHasKey($newSpan->getId(), $this->idToSpan);
        $this->idToSpan[$newSpan->getId()] = $newSpan;
    }

    /**
     * @return array<string, SpanDataInterface>
     */
    public function getIdToSpan(): array
    {
        return $this->idToSpan;
    }

    /**
     * @return SpanDataInterface
     */
    public function getSingleSpan(): SpanDataInterface
    {
        TestCase::assertCount(1, $this->idToSpan);
        return $this->idToSpan[array_key_first($this->idToSpan)];
    }

    /**
     * @param string $name
     *
     * @return SpanDataInterface
     * @throws NotFoundException
     */
    public function getSpanByName(string $name): SpanDataInterface
    {
        foreach ($this->idToSpan as $id => $span) {
            if ($span->getName() === $name) {
                return $span;
            }
        }
        throw new NotFoundException("Span with the name `$name' not found");
    }

    /**
     * @param TransactionDataInterface $transaction
     *
     * @return array<string, SpanDataInterface>
     */
    public function getSpansForTransaction(TransactionDataInterface $transaction): array
    {
        $idToSpanFromTransaction = [];

        foreach ($this->idToSpan as $id => $span) {
            if ($span->getTransactionId() === $transaction->getId()) {
                $idToSpanFromTransaction[$id] = $span;
            }
        }

        return $idToSpanFromTransaction;
    }
}
