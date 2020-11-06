<?php

declare(strict_types=1);

namespace ElasticApmTests\Util\Deserialization;

use Closure;
use Elastic\Apm\Impl\Metadata;
use Elastic\Apm\Impl\SpanData;
use Elastic\Apm\Impl\TransactionData;
use Elastic\Apm\Impl\Util\JsonUtil;
use ElasticApmTests\Util\ValidationUtil;

trait SerializedEventSinkTrait
{
    /** @var bool */
    public $shouldValidateAgainstSchema = true;

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
        $deserializedJson = JsonUtil::decode($serializedData, /* asAssocArray */ true);
        $deserializedData = $deserialize($deserializedJson);
        $assertValid($deserializedData);
        return $deserializedData;
    }

    protected function validateAndDeserializeMetadata(string $serializedMetadata): Metadata
    {
        return self::validateAndDeserialize(
            $serializedMetadata,
            function (string $serializedData): void {
                if ($this->shouldValidateAgainstSchema) {
                    ServerApiSchemaValidator::validateMetadata($serializedData);
                }
            },
            function ($deserializedRawData): Metadata {
                return MetadataDeserializer::deserialize($deserializedRawData);
            },
            function (Metadata $data): void {
                ValidationUtil::assertValidMetadata($data);
            }
        );
    }

    protected function validateAndDeserializeTransactionData(
        string $serializedTransactionData
    ): TransactionData {
        return self::validateAndDeserialize(
            $serializedTransactionData,
            function (string $serializedData): void {
                if ($this->shouldValidateAgainstSchema) {
                    ServerApiSchemaValidator::validateTransactionData($serializedData);
                }
            },
            function ($deserializedRawData): TransactionData {
                return TransactionDataDeserializer::deserialize($deserializedRawData);
            },
            function (TransactionData $data): void {
                ValidationUtil::assertValidTransactionData($data);
            }
        );
    }

    protected function validateAndDeserializeSpanData(string $serializedSpanData): SpanData
    {
        return self::validateAndDeserialize(
            $serializedSpanData,
            function (string $serializedSpanData): void {
                if ($this->shouldValidateAgainstSchema) {
                    ServerApiSchemaValidator::validateSpanData($serializedSpanData);
                }
            },
            function ($deserializedRawData): SpanData {
                return SpanDataDeserializer::deserialize($deserializedRawData);
            },
            function (SpanData $data): void {
                ValidationUtil::assertValidSpanData($data);
            }
        );
    }
}
