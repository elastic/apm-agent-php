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

namespace ElasticApmTests\Util\Deserialization;

use Closure;
use Elastic\Apm\Impl\ErrorData;
use Elastic\Apm\Impl\Metadata;
use Elastic\Apm\Impl\MetricSetData;
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
        /** @var array<string, mixed> $deserializedJson */
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

    protected function validateAndDeserializeErrorData(string $serializedErrorData): ErrorData
    {
        return self::validateAndDeserialize(
            $serializedErrorData,
            function (string $serializedData): void {
                if ($this->shouldValidateAgainstSchema) {
                    ServerApiSchemaValidator::validateErrorData($serializedData);
                }
            },
            function ($deserializedRawData): ErrorData {
                return ErrorDataDeserializer::deserialize($deserializedRawData);
            },
            function (ErrorData $data): void {
                ValidationUtil::assertValidErrorData($data);
            }
        );
    }

    protected function validateAndDeserializeMetricSetData(string $serializedMetricSetData): MetricSetData
    {
        return self::validateAndDeserialize(
            $serializedMetricSetData,
            function (string $serializedSpanData): void {
                if ($this->shouldValidateAgainstSchema) {
                    ServerApiSchemaValidator::validateMetricSetData($serializedSpanData);
                }
            },
            function ($deserializedRawData): MetricSetData {
                return MetricSetDataDeserializer::deserialize($deserializedRawData);
            },
            function (MetricSetData $data): void {
                ValidationUtil::assertValidMetricSetData($data);
            }
        );
    }
}
