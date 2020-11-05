<?php

declare(strict_types=1);

namespace ElasticApmTests\Util\Deserialization;

use Closure;
use Elastic\Apm\Impl\MetadataInterface;
use Elastic\Apm\Impl\Util\JsonUtil;
use Elastic\Apm\SpanDataInterface;
use ElasticApmTests\Util\ValidationUtil;
use Elastic\Apm\TransactionDataInterface;

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

    /** @inheritDoc */
    protected function validateAndDeserializeMetadata(string $serializedMetadata): MetadataInterface
    {
        return self::validateAndDeserialize(
            $serializedMetadata,
            function (string $serializedData): void {
                if ($this->shouldValidateAgainstSchema) {
                    ServerApiSchemaValidator::validateMetadata($serializedData);
                }
            },
            function ($deserializedRawData): MetadataInterface {
                return MetadataDeserializer::deserialize($deserializedRawData);
            },
            function (MetadataInterface $data): void {
                ValidationUtil::assertValidMetadata($data);
            }
        );
    }

    protected function validateAndDeserializeTransactionData(
        string $serializedTransactionData
    ): TransactionDataInterface {
        return self::validateAndDeserialize(
            $serializedTransactionData,
            function (string $serializedData): void {
                if ($this->shouldValidateAgainstSchema) {
                    ServerApiSchemaValidator::validateTransactionData($serializedData);
                }
            },
            function ($deserializedRawData): TransactionDataInterface {
                return TransactionDataDeserializer::deserialize($deserializedRawData);
            },
            function (TransactionDataInterface $data): void {
                ValidationUtil::assertValidTransactionData($data);
            }
        );
    }

    protected function validateAndDeserializeSpanData(string $serializedSpanData): SpanDataInterface
    {
        return self::validateAndDeserialize(
            $serializedSpanData,
            function (string $serializedSpanData): void {
                if ($this->shouldValidateAgainstSchema) {
                    ServerApiSchemaValidator::validateSpanData($serializedSpanData);
                }
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
