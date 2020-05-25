<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\Util\Deserialization;

use Closure;
use Elastic\Apm\Impl\MetadataInterface;
use Elastic\Apm\SpanDataInterface;
use Elastic\Apm\Tests\Util\ValidationUtil;
use Elastic\Apm\TransactionDataInterface;

trait SerializedEventSinkTrait
{
    /**
     * @param string  $serializedData
     * @param Closure $validateAgainstSchema
     * @param Closure $deserialize
     * @param Closure $assertValid
     *
     * @return  mixed
     *
     * @template        T of object
     * @phpstan-param   Closure(string): void $validateAgainstSchema
     * @phpstan-param   Closure(array<string, mixed>): T $deserialize
     * @phpstan-param   Closure(T): void $assertValid
     * @phpstan-return  T
     */
    private static function validateAndDeserialize(
        string $serializedData,
        Closure $validateAgainstSchema,
        Closure $deserialize,
        Closure $assertValid
    ) {
        $validateAgainstSchema($serializedData);
        $deserializedJson = SerializationTestUtil::deserializeJson($serializedData, /* asAssocArray */ true);
        $deserializedData = $deserialize($deserializedJson);
        $assertValid($deserializedData);
        return $deserializedData;
    }

    /** @inheritDoc */
    protected static function validateAndDeserializeMetadata(string $serializedMetadata): MetadataInterface
    {
        return self::validateAndDeserialize(
            $serializedMetadata,
            function (string $serializedData): void {
                ServerApiSchemaValidator::validateMetadata($serializedData);
            },
            function ($deserializedRawData): MetadataInterface {
                return MetadataDeserializer::deserialize($deserializedRawData);
            },
            function (MetadataInterface $data): void {
                ValidationUtil::assertValidMetadata($data);
            }
        );
    }

    protected static function validateAndDeserializeTransactionData(
        string $serializedTransactionData
    ): TransactionDataInterface {
        return self::validateAndDeserialize(
            $serializedTransactionData,
            function (string $serializedData): void {
                ServerApiSchemaValidator::validateTransactionData($serializedData);
            },
            function ($deserializedRawData): TransactionDataInterface {
                return TransactionDataDeserializer::deserialize($deserializedRawData);
            },
            function (TransactionDataInterface $data): void {
                ValidationUtil::assertValidTransactionData($data);
            }
        );
    }

    protected static function validateAndDeserializeSpanData(string $serializedSpanData): SpanDataInterface
    {
        return self::validateAndDeserialize(
            $serializedSpanData,
            function (string $serializedSpanData): void {
                ServerApiSchemaValidator::validateSpanData($serializedSpanData);
            },
            function ($deserializedRawData): SpanDataInterface {
                return SpanDataDeserializer::deserialize($deserializedRawData);
            },
            function (SpanDataInterface $data): void {
                ValidationUtil::assertValidSpanData($data);
            }
        );
    }
}
