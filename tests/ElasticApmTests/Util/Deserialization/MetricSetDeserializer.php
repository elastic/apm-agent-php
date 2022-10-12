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

use Elastic\Apm\Impl\MetricSet;
use ElasticApmTests\Util\AssertValidTrait;
use ElasticApmTests\Util\MetricSetValidator;

final class MetricSetDeserializer
{
    use AssertValidTrait;

    /**
     * @param mixed $value
     *
     * @return MetricSet
     */
    public static function deserialize($value): MetricSet
    {
        $result = new MetricSet();
        DeserializationUtil::deserializeKeyValuePairs(
            DeserializationUtil::assertDecodedJsonMap($value),
            function ($key, $value) use ($result): bool {
                switch ($key) {
                    case 'timestamp':
                        $result->timestamp = self::assertValidTimestamp($value);
                        return true;
                    case 'transaction':
                        self::deserializeTransactionObject($value, $result);
                        return true;
                    case 'span':
                        self::deserializeSpanObject($value, $result);
                        return true;
                    case 'samples':
                        $result->samples = MetricSetValidator::assertValidSamples($value);
                        return true;
                    default:
                        return false;
                }
            }
        );
        MetricSetValidator::assertValid($result);
        return $result;
    }

    /**
     * @param mixed $value
     */
    private static function deserializeTransactionObject($value, MetricSet $result): void
    {
        DeserializationUtil::deserializeKeyValuePairs(
            DeserializationUtil::assertDecodedJsonMap($value),
            function ($key, $value) use ($result): bool {
                switch ($key) {
                    case 'name':
                        $result->transactionName = self::assertValidKeywordString($value);
                        return true;
                    case 'type':
                        $result->transactionType = self::assertValidKeywordString($value);
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
    private static function deserializeSpanObject($value, MetricSet $result): void
    {
        DeserializationUtil::deserializeKeyValuePairs(
            DeserializationUtil::assertDecodedJsonMap($value),
            function ($key, $value) use ($result): bool {
                switch ($key) {
                    case 'subtype':
                        $result->spanSubtype = self::assertValidKeywordString($value);
                        return true;
                    case 'type':
                        $result->spanType = self::assertValidKeywordString($value);
                        return true;
                    default:
                        return false;
                }
            }
        );
    }
}
