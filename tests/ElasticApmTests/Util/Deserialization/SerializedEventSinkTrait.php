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
use Elastic\Apm\Impl\Metadata;
use Elastic\Apm\Impl\MetricSet;
use Elastic\Apm\Impl\Util\JsonUtil;
use ElasticApmTests\Util\AssertMessageStack;
use ElasticApmTests\Util\ErrorDto;
use ElasticApmTests\Util\MetadataValidator;
use ElasticApmTests\Util\MetricSetValidator;
use ElasticApmTests\Util\SpanDto;
use ElasticApmTests\Util\TestCaseBase;
use ElasticApmTests\Util\TransactionDto;

trait SerializedEventSinkTrait
{
    /** @var bool */
    public $shouldValidateAgainstSchema = true;

    /**
     * @param mixed $input
     *
     * @return mixed
     */
    public static function convertObjectToStringKeyArrayRecursively($input)
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, AssertMessageStack::funcArgs());

        if (is_array($input)) {
            $dbgCtx->pushSubScope();
            foreach ($input as $arrKey => $arrValue) {
                $input[$arrKey] = self::convertObjectToStringKeyArrayRecursively($arrValue);
            }
            $dbgCtx->popSubScope();
            return $input;
        }

        if (!is_object($input)) {
            return $input;
        }

        $allKeysAreStrings = true;
        $dbgCtx->pushSubScope();
        foreach (get_object_vars($input) as $propKey => $propValue) {
            $dbgCtx->add(['propKey' => $propKey, 'propValue' => $propValue]);
            if (is_numeric($propKey)) {
                $allKeysAreStrings = false;
            }

            $input->{$propKey} = self::convertObjectToStringKeyArrayRecursively($propValue);
        }
        $dbgCtx->popSubScope();

        if (!$allKeysAreStrings) {
            return $input;
        }

        $asArray = [];
        $dbgCtx->pushSubScope();
        foreach (get_object_vars($input) as $propKey => $propValue) {
            $dbgCtx->add(['propKey' => $propKey, 'propValue' => $propValue]);
            $asArray[$propKey] = $propValue;
        }
        $dbgCtx->popSubScope();
        return $asArray;
    }

    /**
     * @template T of object
     *
     * @param string                   $serializedData
     * @param Closure(string): void    $validateAgainstSchema
     * @param Closure(array<mixed>): T $deserialize
     * @param Closure(T): void         $assertValid
     *
     * @return T
     */
    private static function validateAndDeserialize(string $serializedData, Closure $validateAgainstSchema, Closure $deserialize, Closure $assertValid)
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, ['serializedData' => $serializedData]);

        /** @var object $decodedJson */
        $decodedJson = JsonUtil::decode($serializedData, /* asAssocArray */ false);
        $dbgCtx->add(['decodedJson' => $decodedJson]);

        $postProcessedDecodedJson = self::convertObjectToStringKeyArrayRecursively($decodedJson);
        $dbgCtx->add(['postProcessedDecodedJson' => $postProcessedDecodedJson]);
        TestCaseBase::assertIsArray($postProcessedDecodedJson);

        $validateAgainstSchema($serializedData);
        $deserializedData = $deserialize($postProcessedDecodedJson);
        $dbgCtx->add(['deserializedData' => $deserializedData]);
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
                MetadataValidator::assertValid($data);
            }
        );
    }

    protected function validateAndDeserializeTransaction(string $serializedTransactionData): TransactionDto
    {
        return self::validateAndDeserialize(
            $serializedTransactionData,
            function (string $serializedData): void {
                if ($this->shouldValidateAgainstSchema) {
                    ServerApiSchemaValidator::validateTransaction($serializedData);
                }
            },
            function ($deserializedRawData): TransactionDto {
                return TransactionDto::deserialize($deserializedRawData);
            },
            function (TransactionDto $data): void {
                $data->assertValid();
            }
        );
    }

    protected function validateAndDeserializeSpan(string $serializedSpanData): SpanDto
    {
        return self::validateAndDeserialize(
            $serializedSpanData,
            function (string $serializedSpanData): void {
                if ($this->shouldValidateAgainstSchema) {
                    ServerApiSchemaValidator::validateSpan($serializedSpanData);
                }
            },
            function ($deserializedRawData): SpanDto {
                return SpanDto::deserialize($deserializedRawData);
            },
            function (SpanDto $data): void {
                $data->assertValid();
            }
        );
    }

    protected function validateAndDeserializeError(string $serializedErrorData): ErrorDto
    {
        return self::validateAndDeserialize(
            $serializedErrorData,
            function (string $serializedData): void {
                if ($this->shouldValidateAgainstSchema) {
                    ServerApiSchemaValidator::validateError($serializedData);
                }
            },
            function ($deserializedRawData): ErrorDto {
                return ErrorDto::deserialize($deserializedRawData);
            },
            function (ErrorDto $data): void {
                $data->assertValid();
            }
        );
    }

    protected function validateAndDeserializeMetricSet(string $serializedMetricSet): MetricSet
    {
        return self::validateAndDeserialize(
            $serializedMetricSet,
            function (string $serializedSpanData): void {
                if ($this->shouldValidateAgainstSchema) {
                    ServerApiSchemaValidator::validateMetricSet($serializedSpanData);
                }
            },
            function ($deserializedRawData): MetricSet {
                return MetricSetDeserializer::deserialize($deserializedRawData);
            },
            function (MetricSet $data): void {
                MetricSetValidator::assertValid($data);
            }
        );
    }
}
