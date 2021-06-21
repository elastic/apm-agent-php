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

use Elastic\Apm\Impl\MetricSetData;
use ElasticApmTests\Util\ValidationUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class MetricSetDataDeserializer extends DataDeserializer
{
    /** @var MetricSetData */
    private $result;

    private function __construct(MetricSetData $result)
    {
        $this->result = $result;
    }

    /**
     *
     * @param array<string, mixed> $deserializedRawData
     *
     * @return MetricSetData
     */
    public static function deserialize(array $deserializedRawData): MetricSetData
    {
        $result = new MetricSetData();
        (new self($result))->doDeserialize($deserializedRawData);
        ValidationUtil::assertValidMetricSetData($result);
        return $result;
    }

    /**
     * @param string $key
     * @param mixed  $value
     *
     * @return bool
     */
    protected function deserializeKeyValue(string $key, $value): bool
    {
        switch ($key) {
            // public $transaction = null;

            case 'timestamp':
                $this->result->timestamp = ValidationUtil::assertValidTimestamp($value);
                return true;

            case 'transaction':
                $this->deserializeTransactionObject($value);
                return true;

            case 'span':
                $this->deserializeSpanObject($value);
                return true;

            case 'samples':
                $this->result->samples = ValidationUtil::assertValidMetricSetDataSamples($value);
                return true;

            default:
                return false;
        }
    }

    /**
     * @param array<string, mixed> $deserializedRawData
     */
    private function deserializeTransactionObject(array $deserializedRawData): void
    {
        foreach ($deserializedRawData as $key => $value) {
            switch ($key) {
                case 'name':
                    $this->result->transactionName = ValidationUtil::assertValidKeywordString($value);
                    break;

                case 'type':
                    $this->result->transactionType = ValidationUtil::assertValidKeywordString($value);
                    break;

                default:
                    throw DataDeserializer::buildException("Unknown key: transaction->`$key'");
            }
        }
    }

    /**
     * @param array<string, mixed> $deserializedRawData
     */
    private function deserializeSpanObject(array $deserializedRawData): void
    {
        foreach ($deserializedRawData as $key => $value) {
            switch ($key) {
                case 'subtype':
                    $this->result->spanSubtype = ValidationUtil::assertValidKeywordString($value);
                    break;

                case 'type':
                    $this->result->spanType = ValidationUtil::assertValidKeywordString($value);
                    break;

                default:
                    throw DataDeserializer::buildException("Unknown key: transaction->`$key'");
            }
        }
    }
}
