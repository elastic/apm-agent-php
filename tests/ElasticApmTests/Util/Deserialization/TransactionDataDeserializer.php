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

use Elastic\Apm\Impl\TransactionData;
use Elastic\Apm\Impl\Util\StaticClassTrait;
use ElasticApmTests\Util\DataValidator;
use ElasticApmTests\Util\ExecutionSegmentDataValidator;
use ElasticApmTests\Util\TransactionDataValidator;

final class TransactionDataDeserializer
{
    use StaticClassTrait;

    /**
     * @param mixed $value
     *
     * @return TransactionData
     */
    public static function deserialize($value): TransactionData
    {
        $result = new TransactionData();
        DeserializationUtil::deserializeKeyValuePairs(
            DeserializationUtil::assertDecodedJsonMap($value),
            function ($key, $value) use ($result): bool {
                if (ExecutionSegmentDataDeserializer::deserializeKeyValue($key, $value, $result)) {
                    return true;
                }

                switch ($key) {
                    case 'context':
                        $result->context = TransactionContextDataDeserializer::deserialize($value);
                        return true;
                    case 'parent_id':
                        $result->parentId = ExecutionSegmentDataValidator::validateId($value);
                        return true;
                    case 'result':
                        $result->result = DataValidator::validateKeywordString($value);
                        return true;
                    case 'span_count':
                        self::deserializeSpanCountSubObject($value, $result);
                        return true;
                    case 'sampled':
                        $result->isSampled = DataValidator::validateBool($value);
                        return true;
                    default:
                        return false;
                }
            }
        );
        TransactionDataValidator::validate($result);
        return $result;
    }

    /**
     * @param mixed $value
     */
    private static function deserializeSpanCountSubObject($value, TransactionData $result): void
    {
        DeserializationUtil::deserializeKeyValuePairs(
            DeserializationUtil::assertDecodedJsonMap($value),
            function ($key, $value) use ($result): bool {
                switch ($key) {
                    case 'dropped':
                        $result->droppedSpansCount = TransactionDataValidator::validateCount($value);
                        return true;
                    case 'started':
                        $result->startedSpansCount = TransactionDataValidator::validateCount($value);
                        return true;
                    default:
                        return false;
                }
            }
        );
    }
}
