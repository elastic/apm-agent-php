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
use ElasticApmTests\Util\EventDataValidator;
use ElasticApmTests\Util\ValidationUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class TransactionDataDeserializer extends ExecutionSegmentDataDeserializer
{
    /** @var TransactionData */
    private $result;

    private function __construct(TransactionData $result)
    {
        parent::__construct($result);
        $this->result = $result;
    }

    /**
     * @param mixed $deserializedRawData
     *
     * @return TransactionData
     */
    public static function deserialize($deserializedRawData): TransactionData
    {
        $result = new TransactionData();
        (new self($result))->doDeserialize($deserializedRawData);
        ValidationUtil::assertValidTransactionData($result);
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
        if (parent::deserializeKeyValue($key, $value)) {
            return true;
        }

        switch ($key) {
            case 'context':
                $this->result->context = TransactionContextDataDeserializer::deserialize($value);
                return true;

            case 'parent_id':
                $this->result->parentId = ValidationUtil::assertValidExecutionSegmentId($value);
                return true;

            case 'result':
                $this->result->result = ValidationUtil::assertValidKeywordString($value);
                return true;

            case 'span_count':
                $this->deserializeSpanCountSubObject($value);
                return true;

            case 'sampled':
                $this->result->isSampled = EventDataValidator::validateBool($value);
                return true;

            default:
                return false;
        }
    }

    /**
     * @param mixed $deserializedRawData
     */
    private function deserializeSpanCountSubObject($deserializedRawData): void
    {
        ValidationUtil::assertThat(is_array($deserializedRawData));
        /** @var array<mixed, mixed> $deserializedRawData */

        foreach ($deserializedRawData as $key => $value) {
            switch ($key) {
                case 'dropped':
                    $this->result->droppedSpansCount
                        = ValidationUtil::assertValidCount($value);
                    break;

                case 'started':
                    $this->result->startedSpansCount
                        = ValidationUtil::assertValidCount($value);
                    break;

                default:
                    throw DataDeserializer::buildException("Unknown key: span_count->`$key'");
            }
        }
    }
}
