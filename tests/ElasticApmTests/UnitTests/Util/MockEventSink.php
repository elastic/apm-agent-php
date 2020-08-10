<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\UnitTests\Util;

use Closure;
use Elastic\Apm\Impl\EventSinkInterface;
use Elastic\Apm\Impl\Util\SerializationUtil;
use Elastic\Apm\Metadata;
use Elastic\Apm\SpanInterface;
use Elastic\Apm\Tests\Util\ProdObjToTestDtoConverter;
use Elastic\Apm\Tests\Util\SerializedEventSinkTrait;
use Elastic\Apm\Tests\Util\ValidationUtil;
use Elastic\Apm\TransactionInterface;
use PHPUnit\Framework\TestCase;

class MockEventSink implements EventSinkInterface
{
    use SerializedEventSinkTrait;

    /** @var Metadata[] */
    private $metadata = [];

    /** @var array<string, TransactionInterface> */
    private $idToTransaction = [];

    /** @var array<string, SpanInterface> */
    private $idToSpan = [];

    /**
     * @param object $inputProdObj
     * @param Closure(object): void $assertValid
     * @param Closure(object): string $serialize
     * @param Closure(string): object $validateAndDeserialize
     * @param Closure(object, object): void $assertEquals
     *
     * @return object
     *
     * @template        T of object
     * @phpstan-param   T $inputProdObj
     * @phpstan-param   Closure(T): void $assertValid
     * @phpstan-param   Closure(T): string $serialize
     * @phpstan-param   Closure(string): T $validateAndDeserialize
     * @phpstan-param   Closure(T, T): void $assertEquals
     * @phpstan-return  T
     */
    private function passThroughSerialization(
        object $inputProdObj,
        Closure $assertValid,
        Closure $serialize,
        Closure $validateAndDeserialize,
        Closure $assertEquals
    ): object {
        $assertValid($inputProdObj);
        $serializedAsJson = $serialize($inputProdObj);
        $deserializedAsDto = $validateAndDeserialize($serializedAsJson);
        $assertValid($deserializedAsDto);
        $assertEquals($inputProdObj, $deserializedAsDto);
        return $deserializedAsDto;
    }

    public function setMetadata(Metadata $metadata): void
    {
        $this->metadata[] = self::passThroughSerialization(
            $metadata,
            /* assertValid: */
            function (Metadata $metadataToValidate): void {
                ValidationUtil::assertValidMetadata($metadataToValidate);
                self::additionalMetadataValidation($metadataToValidate);
            },
            /* serialize: */
            function (Metadata $metadataToSerialize): string {
                return SerializationUtil::serializeAsJson($metadataToSerialize);
            },
            /* validateAndDeserialize: */
            function (string $serializedMetadata): Metadata {
                return self::validateAndDeserializeMetadata($serializedMetadata);
            },
            /* assertEquals: */
            function ($inputMetadata, $deserializedMetadata): void {
                ValidationUtil::assertThat($inputMetadata == $deserializedMetadata);
            }
        );
    }

    public static function additionalMetadataValidation(Metadata $metadata): void
    {
        TestCase::assertSame(getmypid(), $metadata->process()->getPid());
        TestCase::assertNotNull($metadata->service()->language());
        TestCase::assertSame(PHP_VERSION, $metadata->service()->language()->getVersion());
    }

    public function consumeTransaction(TransactionInterface $transaction): void
    {
        /** @var TransactionInterface $newTransaction */
        $newTransaction = self::passThroughSerialization(
            $transaction,
            /* assertValid: */
            function (TransactionInterface $transactionToValidate): void {
                ValidationUtil::assertValidTransaction($transactionToValidate);
            },
            /* serialize: */
            function (TransactionInterface $transactionToSerialize): string {
                return SerializationUtil::serializeAsJson($transactionToSerialize);
            },
            /* validateAndDeserialize: */
            function (string $serializedTransaction): TransactionInterface {
                return self::validateAndDeserializeTransaction($serializedTransaction);
            },
            /* assertEquals: */
            function ($inputTransaction, $deserializedTransaction): void {
                ValidationUtil::assertThat(
                    ProdObjToTestDtoConverter::convertTransaction($inputTransaction) == $deserializedTransaction
                );
            }
        );
        TestCase::assertArrayNotHasKey($newTransaction->getId(), $this->idToTransaction);
        $this->idToTransaction[$newTransaction->getId()] = $newTransaction;
    }

    /**
     * @return array<string, TransactionInterface>
     */
    public function getIdToTransaction(): array
    {
        return $this->idToTransaction;
    }

    /**
     * @return TransactionInterface
     */
    public function getSingleTransaction(): TransactionInterface
    {
        TestCase::assertCount(1, $this->idToTransaction);
        return $this->idToTransaction[array_key_first($this->idToTransaction)];
    }

    public function consumeSpan(SpanInterface $span): void
    {
        /** @var SpanInterface $newSpan */
        $newSpan = self::passThroughSerialization(
            $span,
            /* assertValid: */
            function (SpanInterface $spanToValidate): void {
                ValidationUtil::assertValidSpan($spanToValidate);
            },
            /* serialize: */
            function (SpanInterface $spanToSerialize): string {
                return SerializationUtil::serializeAsJson($spanToSerialize);
            },
            /* validateAndDeserialize: */
            function (string $serializedSpan): SpanInterface {
                return self::validateAndDeserializeSpan($serializedSpan);
            },
            /* assertEquals: */
            function ($data, $deserializedData): void {
                ValidationUtil::assertThat(ProdObjToTestDtoConverter::convertSpan($data) == $deserializedData);
            }
        );
        TestCase::assertArrayNotHasKey($newSpan->getId(), $this->idToSpan);
        $this->idToSpan[$newSpan->getId()] = $newSpan;
    }

    /**
     * @return array<string, SpanInterface>
     */
    public function getIdToSpan(): array
    {
        return $this->idToSpan;
    }

    /**
     * @return SpanInterface
     */
    public function getSingleSpan(): SpanInterface
    {
        TestCase::assertCount(1, $this->idToSpan);
        return $this->idToSpan[array_key_first($this->idToSpan)];
    }

    /**
     * @param string $name
     *
     * @return SpanInterface
     * @throws NotFoundException
     */
    public function getSpanByName(string $name): SpanInterface
    {
        foreach ($this->idToSpan as $id => $span) {
            if ($span->getName() === $name) {
                return $span;
            }
        }
        throw new NotFoundException("Span with the name `$name' not found");
    }

    /**
     * @param TransactionInterface $transaction
     *
     * @return array<string, SpanInterface>
     */
    public function getSpansForTransaction(TransactionInterface $transaction): array
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
