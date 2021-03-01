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

use Elastic\Apm\Impl\ServiceAgentData;
use ElasticApmTests\Util\ValidationUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class ServiceAgentDataDeserializer extends DataDeserializer
{
    /** @var ServiceAgentData */
    private $result;

    /** @var NameVersionDataDeserializer */
    private $parentDeserializer;

    private function __construct(ServiceAgentData $result)
    {
        $this->parentDeserializer = new NameVersionDataDeserializer($result);
        $this->result = $result;
    }

    /**
     *
     * @param array<string, mixed> $deserializedRawData
     *
     * @return ServiceAgentData
     */
    public static function deserialize(array $deserializedRawData): ServiceAgentData
    {
        $result = new ServiceAgentData();
        (new self($result))->doDeserialize($deserializedRawData);
        ValidationUtil::assertValidNameVersionData($result);
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
        if ($this->parentDeserializer->deserializeKeyValue($key, $value)) {
            return true;
        }

        switch ($key) {
            case 'ephemeral_id':
                $this->result->ephemeralId = ValidationUtil::assertValidKeywordString($value);
                return true;

            default:
                return false;
        }
    }
}
