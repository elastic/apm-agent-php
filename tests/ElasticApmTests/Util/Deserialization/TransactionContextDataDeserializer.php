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

use Elastic\Apm\Impl\TransactionContextData;
use Elastic\Apm\Impl\TransactionContextRequestData;
use Elastic\Apm\Impl\TransactionContextRequestUrlData;
use Elastic\Apm\Impl\Util\StaticClassTrait;
use ElasticApmTests\Util\DataValidator;
use ElasticApmTests\Util\TransactionDataValidator;

final class TransactionContextDataDeserializer
{
    use StaticClassTrait;

    /**
     * @param mixed $value
     *
     * @return TransactionContextData
     */
    public static function deserialize($value): TransactionContextData
    {
        $result = new TransactionContextData();
        DeserializationUtil::deserializeKeyValuePairs(
            DeserializationUtil::assertDecodedJsonMap($value),
            function ($key, $value) use ($result): bool {
                if (ExecutionSegmentContextDataDeserializer::deserializeKeyValue($key, $value, $result)) {
                    return true;
                }

                switch ($key) {
                    case 'request':
                        $result->request = self::deserializeRequestData($value);
                        return true;
                    default:
                        return false;
                }
            }
        );
        TransactionDataValidator::validateContextData($result);
        return $result;
    }

    /**
     * @param mixed $value
     *
     * @return TransactionContextRequestData
     */
    private static function deserializeRequestData($value): TransactionContextRequestData
    {
        $result = new TransactionContextRequestData();
        DeserializationUtil::deserializeKeyValuePairs(
            DeserializationUtil::assertDecodedJsonMap($value),
            function ($key, $value) use ($result): bool {
                switch ($key) {
                    case 'method':
                        $result->method = DataValidator::validateNullableKeywordString($value);
                        return true;
                    case 'url':
                        $result->url = self::deserializeRequestUrlData($value);
                        return true;
                    default:
                        return false;
                }
            }
        );
        TransactionDataValidator::validateContextRequestData($result);
        return $result;
    }

    /**
     * @param mixed $value
     *
     * @return TransactionContextRequestUrlData
     */
    private static function deserializeRequestUrlData($value): TransactionContextRequestUrlData
    {
        $result = new TransactionContextRequestUrlData();
        DeserializationUtil::deserializeKeyValuePairs(
            DeserializationUtil::assertDecodedJsonMap($value),
            function ($key, $value) use ($result): bool {
                switch ($key) {
                    case 'full':
                        $result->full = DataValidator::validateNullableKeywordString($value);
                        return true;
                    case 'hostname':
                        $result->domain = DataValidator::validateNullableKeywordString($value);
                        return true;
                    case 'pathname':
                        $result->path = DataValidator::validateNullableKeywordString($value);
                        return true;
                    case 'port':
                        $result->port = TransactionDataValidator::validateNullablePort($value);
                        return true;
                    case 'protocol':
                        $result->protocol = DataValidator::validateNullableKeywordString($value);
                        return true;
                    case 'raw':
                        $result->original = DataValidator::validateNullableKeywordString($value);
                        return true;
                    case 'search':
                        $result->query = DataValidator::validateNullableKeywordString($value);
                        return true;
                    default:
                        return false;
                }
            }
        );
        TransactionDataValidator::validateContextRequestUrlData($result);
        return $result;
    }
}
