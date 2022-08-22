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
use ElasticApmTests\Util\DataValidator;
use ElasticApmTests\Util\MetricSetDataValidator;

final class MetricSetDataDeserializer
{
    /**
     * @param mixed $value
     *
     * @return MetricSetData
     */
    public static function deserialize($value): MetricSetData
    {
        $result = new MetricSetData();
        DeserializationUtil::deserializeKeyValuePairs(
            DeserializationUtil::assertDecodedJsonMap($value),
            function ($key, $value) use ($result): bool {
                switch ($key) {
                    case 'timestamp':
                        $result->timestamp = DataValidator::validateTimestamp($value);
                        return true;
                    case 'transaction':
                        self::deserializeTransactionObject($value, $result);
                        return true;
                    case 'span':
                        self::deserializeSpanObject($value, $result);
                        return true;
                    case 'samples':
                        $result->samples = MetricSetDataValidator::validateSamples($value);
                        return true;
                    default:
                        return false;
                }
            }
        );
        MetricSetDataValidator::validate($result);
        return $result;
    }

    /**
     * @param mixed $value
     */
    private static function deserializeTransactionObject($value, MetricSetData $result): void
    {
        DeserializationUtil::deserializeKeyValuePairs(
            DeserializationUtil::assertDecodedJsonMap($value),
            function ($key, $value) use ($result): bool {
                switch ($key) {
                    case 'name':
                        $result->transactionName = DataValidator::validateKeywordString($value);
                        return true;
                    case 'type':
                        $result->transactionType = DataValidator::validateKeywordString($value);
                        return true;
                    default:
                        return false;
                }
            }
        );
    }

    /**
     * @param mixed $value
     */
    private static function deserializeSpanObject($value, MetricSetData $result): void
    {
        DeserializationUtil::deserializeKeyValuePairs(
            DeserializationUtil::assertDecodedJsonMap($value),
            function ($key, $value) use ($result): bool {
                switch ($key) {
                    case 'subtype':
                        $result->spanSubtype = DataValidator::validateKeywordString($value);
                        return true;
                    case 'type':
                        $result->spanType = DataValidator::validateKeywordString($value);
                        return true;
                    default:
                        return false;
                }
            }
        );
    }
}
