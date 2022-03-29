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
use ElasticApmTests\Util\ValidationUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class TransactionContextDataDeserializer extends ExecutionSegmentContextDataDeserializer
{
    /** @var TransactionContextData */
    private $result;

    private function __construct(TransactionContextData $result)
    {
        parent::__construct($result);
        $this->result = $result;
    }

    /**
     *
     * @param mixed $deserializedRawData
     *
     * @return TransactionContextData
     */
    public static function deserialize($deserializedRawData): TransactionContextData
    {
        $result = new TransactionContextData();
        (new self($result))->doDeserialize($deserializedRawData);
        ValidationUtil::assertValidTransactionContextData($result);
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
            case 'request':
                $this->result->request = TransactionContextRequestDataDeserializer::deserialize($value);
                return true;

            default:
                return false;
        }
    }
}
