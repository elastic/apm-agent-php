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

use Elastic\Apm\Impl\SpanContextData;
use Elastic\Apm\Impl\SpanContextDbData;
use Elastic\Apm\Impl\SpanContextDestinationData;
use Elastic\Apm\Impl\SpanContextDestinationServiceData;
use Elastic\Apm\Impl\SpanContextHttpData;
use Elastic\Apm\Impl\SpanContextServiceData;
use Elastic\Apm\Impl\SpanContextServiceTargetData;
use Elastic\Apm\Impl\SpanData;
use Elastic\Apm\Impl\Util\StaticClassTrait;
use ElasticApmTests\Util\DataValidator;
use ElasticApmTests\Util\ExecutionSegmentDataValidator;
use ElasticApmTests\Util\SpanContextDataValidator;
use ElasticApmTests\Util\SpanContextDbDataValidator;
use ElasticApmTests\Util\SpanContextDestinationDataValidator;
use ElasticApmTests\Util\SpanContextDestinationServiceDataValidator;
use ElasticApmTests\Util\SpanContextHttpDataValidator;
use ElasticApmTests\Util\SpanContextServiceDataValidator;
use ElasticApmTests\Util\SpanContextServiceTargetDataValidator;
use ElasticApmTests\Util\SpanDataValidator;

final class SpanDataDeserializer
{
    use StaticClassTrait;

    /**
     * @param mixed $value
     *
     * @return SpanData
     */
    public static function deserialize($value): SpanData
    {
        $result = new SpanData();
        DeserializationUtil::deserializeKeyValuePairs(
            DeserializationUtil::assertDecodedJsonMap($value),
            function ($key, $value) use ($result): bool {
                if (ExecutionSegmentDataDeserializer::deserializeKeyValue($key, $value, $result)) {
                    return true;
                }

                switch ($key) {
                    case 'action':
                        $result->action = DataValidator::validateKeywordString($value);
                        return true;
                    case 'context':
                        $result->context = self::deserializeContextData($value);
                        return true;
                    case 'parent_id':
                        $result->parentId = ExecutionSegmentDataValidator::validateId($value);
                        return true;
                    case 'stacktrace':
                        $result->stacktrace = StacktraceDeserializer::deserialize($value);
                        return true;
                    case 'subtype':
                        $result->subtype = DataValidator::validateKeywordString($value);
                        return true;
                    case 'transaction_id':
                        $result->transactionId = ExecutionSegmentDataValidator::validateId($value);
                        return true;
                    default:
                        return false;
                }
            }
        );
        SpanDataValidator::validate($result);
        return $result;
    }

    /**
     * @param mixed $value
     *
     * @return SpanContextData
     */
    private static function deserializeContextData($value): SpanContextData
    {
        $result = new SpanContextData();
        DeserializationUtil::deserializeKeyValuePairs(
            DeserializationUtil::assertDecodedJsonMap($value),
            function ($key, $value) use ($result): bool {
                if (ExecutionSegmentContextDataDeserializer::deserializeKeyValue($key, $value, $result)) {
                    return true;
                }

                switch ($key) {
                    case 'db':
                        $result->db = self::deserializeContextDbData($value);
                        return true;
                    case 'destination':
                        $result->destination = self::deserializeContextDestinationData($value);
                        return true;
                    case 'http':
                        $result->http = self::deserializeContextHttpData($value);
                        return true;
                    case 'service':
                        $result->service = self::deserializeContextServiceData($value);
                        return true;
                    default:
                        return false;
                }
            }
        );
        SpanContextDataValidator::validate($result);
        return $result;
    }

    /**
     * @param mixed $value
     *
     * @return SpanContextDbData
     */
    private static function deserializeContextDbData($value): SpanContextDbData
    {
        $result = new SpanContextDbData();
        DeserializationUtil::deserializeKeyValuePairs(
            DeserializationUtil::assertDecodedJsonMap($value),
            function ($key, $value) use ($result): bool {
                switch ($key) {
                    case 'statement':
                        $result->statement = DataValidator::validateNullableNonKeywordString($value);
                        return true;
                    default:
                        return false;
                }
            }
        );
        SpanContextDbDataValidator::validate($result);
        return $result;
    }

    /**
     * @param mixed $value
     *
     * @return SpanContextDestinationData
     */
    private static function deserializeContextDestinationData($value): SpanContextDestinationData
    {
        $result = new SpanContextDestinationData();
        DeserializationUtil::deserializeKeyValuePairs(
            DeserializationUtil::assertDecodedJsonMap($value),
            function ($key, $value) use ($result): bool {
                switch ($key) {
                    case 'service':
                        $result->service = self::deserializeContextDestinationServiceData($value);
                        return true;
                    default:
                        return false;
                }
            }
        );
        SpanContextDestinationDataValidator::validate($result);
        return $result;
    }

    /**
     * @param mixed $value
     *
     * @return SpanContextDestinationServiceData
     */
    private static function deserializeContextDestinationServiceData($value): SpanContextDestinationServiceData
    {
        $result = new SpanContextDestinationServiceData();
        DeserializationUtil::deserializeKeyValuePairs(
            DeserializationUtil::assertDecodedJsonMap($value),
            function ($key, $value) use ($result): bool {
                switch ($key) {
                    case 'name':
                        $result->name = DataValidator::validateKeywordString($value);
                        return true;
                    case 'resource':
                        $result->resource = DataValidator::validateKeywordString($value);
                        return true;
                    case 'type':
                        $result->type = DataValidator::validateKeywordString($value);
                        return true;
                    default:
                        return false;
                }
            }
        );
        SpanContextDestinationServiceDataValidator::validate($result);
        return $result;
    }

    /**
     * @param mixed $value
     *
     * @return SpanContextHttpData
     */
    private static function deserializeContextHttpData($value): SpanContextHttpData
    {
        $result = new SpanContextHttpData();
        DeserializationUtil::deserializeKeyValuePairs(
            DeserializationUtil::assertDecodedJsonMap($value),
            function ($key, $value) use ($result): bool {
                switch ($key) {
                    case 'url':
                        $result->url = DataValidator::validateNullableNonKeywordString($value);
                        return true;
                    case 'status_code':
                        $result->statusCode = DataValidator::validateNullableHttpStatusCode($value);
                        return true;
                    case 'method':
                        $result->method = DataValidator::validateNullableKeywordString($value);
                        return true;
                    default:
                        return false;
                }
            }
        );
        SpanContextHttpDataValidator::validate($result);
        return $result;
    }

    /**
     * @param mixed $value
     *
     * @return SpanContextServiceData
     */
    private static function deserializeContextServiceData($value): SpanContextServiceData
    {
        $result = new SpanContextServiceData();
        DeserializationUtil::deserializeKeyValuePairs(
            DeserializationUtil::assertDecodedJsonMap($value),
            function ($key, $value) use ($result): bool {
                switch ($key) {
                    case 'target':
                        $result->target = self::deserializeContextServiceDataTargetData($value);
                        return true;
                    default:
                        return false;
                }
            }
        );
        SpanContextServiceDataValidator::validate($result);
        return $result;
    }

    /**
     * @param mixed $value
     *
     * @return SpanContextServiceTargetData
     */
    private static function deserializeContextServiceDataTargetData($value): SpanContextServiceTargetData
    {
        $result = new SpanContextServiceTargetData();
        DeserializationUtil::deserializeKeyValuePairs(
            DeserializationUtil::assertDecodedJsonMap($value),
            function ($key, $value) use ($result): bool {
                switch ($key) {
                    case 'name':
                        $result->name = DataValidator::validateNullableKeywordString($value);
                        return true;
                    case 'type':
                        $result->type = DataValidator::validateNullableKeywordString($value);
                        return true;
                    default:
                        return false;
                }
            }
        );
        SpanContextServiceTargetDataValidator::validate($result);
        return $result;
    }

    /**
     * @param mixed $value
     *
     * @return SpanContextServiceData
     */
    private static function deserializeContextServiceData($value): SpanContextServiceData
    {
        $result = new SpanContextServiceData();
        DeserializationUtil::deserializeKeyValuePairs(
            DeserializationUtil::assertDecodedJsonMap($value),
            function ($key, $value) use ($result): bool {
                switch ($key) {
                    case 'target':
                        $result->target = self::deserializeContextServiceDataTargetData($value);
                        return true;
                    default:
                        return false;
                }
            }
        );
        SpanDataValidator::validateContextServiceData($result);
        return $result;
    }

    /**
     * @param mixed $value
     *
     * @return SpanContextServiceTargetData
     */
    private static function deserializeContextServiceDataTargetData($value): SpanContextServiceTargetData
    {
        $result = new SpanContextServiceTargetData();
        DeserializationUtil::deserializeKeyValuePairs(
            DeserializationUtil::assertDecodedJsonMap($value),
            function ($key, $value) use ($result): bool {
                switch ($key) {
                    case 'name':
                        $result->name = DataValidator::validateNullableKeywordString($value);
                        return true;
                    case 'type':
                        $result->type = DataValidator::validateNullableKeywordString($value);
                        return true;
                    default:
                        return false;
                }
            }
        );
        SpanDataValidator::validateContextServiceTargetData($result);
        return $result;
    }
}
