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

use Elastic\Apm\Impl\SpanData;
use ElasticApmTests\Util\ValidationUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class SpanDataDeserializer extends ExecutionSegmentDataDeserializer
{
    /** @var SpanData */
    private $result;

    private function __construct(SpanData $result)
    {
        parent::__construct($result);
        $this->result = $result;
    }

    /**
     *
     * @param mixed $deserializedRawData
     *
     * @return SpanData
     */
    public static function deserialize($deserializedRawData): SpanData
    {
        $result = new SpanData();
        (new self($result))->doDeserialize($deserializedRawData);
        ValidationUtil::assertValidSpanData($result);
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
            case 'action':
                $this->result->action = ValidationUtil::assertValidKeywordString($value);
                return true;

            case 'context':
                $this->result->context = SpanContextDataDeserializer::deserialize($value);
                return true;

            case 'parent_id':
                $this->result->parentId = ValidationUtil::assertValidExecutionSegmentId($value);
                return true;

            case 'stacktrace':
                $this->result->stacktrace = StacktraceDeserializer::deserialize($value);
                return true;

            case 'subtype':
                $this->result->subtype = ValidationUtil::assertValidKeywordString($value);
                return true;

            case 'transaction_id':
                $this->result->transactionId = ValidationUtil::assertValidExecutionSegmentId($value);
                return true;

            default:
                return false;
        }
    }
}
