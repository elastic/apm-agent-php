<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\Util;

use Closure;
use Elastic\Apm\Metadata;
use Elastic\Apm\SpanInterface;
use Elastic\Apm\TransactionInterface;

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

    private static function validateAndDeserializeMetadata(string $serializedMetadata): Metadata
    {
        return self::validateAndDeserialize(
            $serializedMetadata,
            /* validateAgainstSchema: */
            function (string $serializedData): void {
                ServerApiSchemaValidator::validateMetadata($serializedData);
            },
            /* deserialize */
            function ($decodedData): Metadata {
                $result = new Metadata();
                Deserializer::deserializeMetadata($decodedData, $result);
                return $result;
            },
            /* assertValid */
            function (Metadata $metadata): void {
                ValidationUtil::assertValidMetadata($metadata);
            }
        );
    }

    private static function validateAndDeserializeTransaction(string $serializedTransaction): TransactionInterface
    {
        return self::validateAndDeserialize(
            $serializedTransaction,
            /* validateAgainstSchema: */
            function (string $serializedData): void {
                ServerApiSchemaValidator::validateTransaction($serializedData);
            },
            /* deserialize */
            function ($decodedData): TransactionInterface {
                $result = new TransactionTestDto();
                Deserializer::deserializeTransaction($decodedData, $result);
                return $result;
            },
            /* assertValid */
            function (TransactionInterface $transaction): void {
                ValidationUtil::assertValidTransaction($transaction);
            }
        );
    }

    private static function validateAndDeserializeSpan(string $serializedSpan): SpanInterface
    {
        return self::validateAndDeserialize(
            $serializedSpan,
            /* validateAgainstSchema: */
            function (string $serializedSpan): void {
                ServerApiSchemaValidator::validateSpan($serializedSpan);
            },
            /* deserialize */
            function ($decodedData): SpanInterface {
                $result = new SpanTestDto();
                Deserializer::deserializeSpan($decodedData, $result);
                return $result;
            },
            /* assertValid */
            function (SpanInterface $span): void {
                ValidationUtil::assertValidSpan($span);
            }
        );
    }
}
