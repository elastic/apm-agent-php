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

use Elastic\Apm\Impl\TransactionContextRequestUrlData;
use ElasticApmTests\Util\ValidationUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class TransactionContextRequestUrlDataDeserializer extends DataDeserializer
{
    /** @var TransactionContextRequestUrlData */
    private $result;

    private function __construct(TransactionContextRequestUrlData $result)
    {
        $this->result = $result;
    }

    /**
     *
     * @param array<string, mixed> $deserializedRawData
     *
     * @return TransactionContextRequestUrlData
     */
    public static function deserialize(array $deserializedRawData): TransactionContextRequestUrlData
    {
        $result = new TransactionContextRequestUrlData();
        (new self($result))->doDeserialize($deserializedRawData);
        ValidationUtil::assertValidTransactionContextRequestUrlData($result);
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
            case 'full':
                $this->result->full = ValidationUtil::assertValidNullableKeywordString($value);
                return true;

            case 'hostname':
                $this->result->domain = ValidationUtil::assertValidNullableKeywordString($value);
                return true;

            case 'pathname':
                $this->result->path = ValidationUtil::assertValidNullableKeywordString($value);
                return true;

            case 'port':
                $this->result->port = ValidationUtil::assertValidNullablePort($value);
                return true;

            case 'protocol':
                $this->result->protocol = ValidationUtil::assertValidNullableKeywordString($value);
                return true;

            case 'raw':
                $this->result->original = ValidationUtil::assertValidNullableKeywordString($value);
                return true;

            case 'search':
                $this->result->query = ValidationUtil::assertValidNullableKeywordString($value);
                return true;

            default:
                return false;
        }
    }
}
