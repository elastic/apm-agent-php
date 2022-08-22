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

use Elastic\Apm\Impl\ExecutionSegmentData;
use Elastic\Apm\Impl\Util\StaticClassTrait;
use ElasticApmTests\Util\DataValidator;
use ElasticApmTests\Util\ExecutionSegmentDataValidator;
use ElasticApmTests\Util\TraceDataValidator;

final class ExecutionSegmentDataDeserializer
{
    use StaticClassTrait;

    /**
     * @param mixed                $key
     * @param mixed                $value
     * @param ExecutionSegmentData $result
     *
     * @return bool
     */
    public static function deserializeKeyValue($key, $value, ExecutionSegmentData $result): bool
    {
        switch ($key) {
            case 'duration':
                $result->duration = DataValidator::validateDuration($value);
                return true;
            case 'id':
                $result->id = ExecutionSegmentDataValidator::validateId($value);
                return true;
            case 'name':
                $result->name = DataValidator::validateKeywordString($value);
                return true;
            case 'outcome':
                $result->outcome = ExecutionSegmentDataValidator::validateOutcome($value);
                return true;
            case 'sample_rate':
                $result->sampleRate = ExecutionSegmentDataValidator::validateNullableSampleRate($value);
                return true;
            case 'timestamp':
                $result->timestamp = DataValidator::validateTimestamp($value);
                return true;
            case 'trace_id':
                $result->traceId = TraceDataValidator::validateId($value);
                return true;
            case 'type':
                $result->type = DataValidator::validateKeywordString($value);
                return true;
            default:
                return false;
        }
    }
}
