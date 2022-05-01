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

use Elastic\Apm\Impl\ErrorData;
use Elastic\Apm\Impl\ErrorExceptionData;
use Elastic\Apm\Impl\ErrorTransactionData;
use Elastic\Apm\Impl\Util\StaticClassTrait;
use ElasticApmTests\Util\DataValidator;
use ElasticApmTests\Util\ErrorDataValidator;
use ElasticApmTests\Util\ExecutionSegmentDataValidator;
use ElasticApmTests\Util\TraceDataValidator;

final class ErrorDataDeserializer
{
    use StaticClassTrait;

    /**
     * @param mixed $value
     *
     * @return ErrorData
     */
    public static function deserialize($value): ErrorData
    {
        $result = new ErrorData();
        DeserializationUtil::deserializeKeyValuePairs(
            DeserializationUtil::assertDecodedJsonMap($value),
            function ($key, $value) use ($result): bool {
                switch ($key) {
                    case 'timestamp':
                        $result->timestamp = DataValidator::validateTimestamp($value);
                        return true;
                    case 'id':
                        $result->id = ErrorDataValidator::validateId($value);
                        return true;
                    case 'trace_id':
                        $result->traceId = TraceDataValidator::validateId($value);
                        return true;
                    case 'transaction_id':
                        $result->transactionId = ExecutionSegmentDataValidator::validateId($value);
                        return true;
                    case 'parent_id':
                        $result->parentId = ExecutionSegmentDataValidator::validateId($value);
                        return true;
                    case 'transaction':
                        $result->transaction = self::deserializeTransactionData($value);
                        return true;
                    case 'context':
                        $result->context = TransactionContextDataDeserializer::deserialize($value);
                        return true;
                    case 'culprit':
                        $result->culprit = DataValidator::validateNullableKeywordString($value);
                        return true;
                    case 'exception':
                        $result->exception = self::deserializeExceptionData($value);
                        return true;
                    default:
                        return false;
                }
            }
        );
        ErrorDataValidator::validate($result);
        return $result;
    }

    /**
     * @param mixed  $value
     *
     * @return ErrorExceptionData
     */
    private static function deserializeExceptionData($value): ErrorExceptionData
    {
        $result = new ErrorExceptionData();
        DeserializationUtil::deserializeKeyValuePairs(
            DeserializationUtil::assertDecodedJsonMap($value),
            function ($key, $value) use ($result): bool {
                switch ($key) {
                    case 'code':
                        $result->code = ErrorDataValidator::validateExceptionDataCode($value);
                        return true;
                    case 'message':
                        $result->message = DataValidator::validateNullableNonKeywordString($value);
                        return true;
                    case 'module':
                        $result->module = DataValidator::validateNullableKeywordString($value);
                        return true;
                    case 'stacktrace':
                        $result->stacktrace = StacktraceDeserializer::deserialize($value);
                        return true;
                    case 'type':
                        $result->type = DataValidator::validateNullableKeywordString($value);
                        return true;
                    default:
                        return false;
                }
            }
        );
        ErrorDataValidator::validateExceptionData($result);
        return $result;
    }

    /**
     * @param mixed  $value
     *
     * @return ErrorTransactionData
     */
    private static function deserializeTransactionData($value): ErrorTransactionData
    {
        $result = new ErrorTransactionData();
        DeserializationUtil::deserializeKeyValuePairs(
            DeserializationUtil::assertDecodedJsonMap($value),
            function ($key, $value) use ($result): bool {
                switch ($key) {
                    case 'sampled':
                        $result->isSampled = DataValidator::validateBool($value);
                        return true;
                    case 'name':
                        $result->name = DataValidator::validateKeywordString($value);
                        return true;
                    case 'type':
                        $result->type = DataValidator::validateKeywordString($value);
                        return true;
                    default:
                        return false;
                }
            }
        );
        ErrorDataValidator::validateTransactionDataEx($result);
        return $result;
    }
}
