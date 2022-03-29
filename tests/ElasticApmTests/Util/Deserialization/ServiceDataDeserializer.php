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

use Elastic\Apm\Impl\ServiceData;
use ElasticApmTests\Util\ValidationUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class ServiceDataDeserializer extends DataDeserializer
{
    /** @var ServiceData */
    private $result;

    private function __construct(ServiceData $result)
    {
        $this->result = $result;
    }

    /**
     *
     * @param mixed $deserializedRawData
     *
     * @return ServiceData
     */
    public static function deserialize($deserializedRawData): ServiceData
    {
        $result = new ServiceData();
        (new self($result))->doDeserialize($deserializedRawData);
        ValidationUtil::assertValidServiceData($result);
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
            case 'agent':
                $this->result->agent = ServiceAgentDataDeserializer::deserialize($value);
                return true;

            case 'environment':
                $this->result->environment = ValidationUtil::assertValidKeywordString($value);
                return true;

            case 'framework':
                $this->result->framework = NameVersionDataDeserializer::deserialize($value);
                return true;

            case 'language':
                $this->result->language = NameVersionDataDeserializer::deserialize($value);
                return true;

            case 'name':
                $this->result->name = ValidationUtil::assertValidKeywordString($value);
                return true;

            case 'node':
                $this->deserializeNodeSubObject($value);
                return true;

            case 'runtime':
                $this->result->runtime = NameVersionDataDeserializer::deserialize($value);
                return true;

            case 'version':
                $this->result->version = ValidationUtil::assertValidKeywordString($value);
                return true;

            default:
                return false;
        }
    }

    /**
     * @param mixed $deserializedRawData
     */
    private function deserializeNodeSubObject($deserializedRawData): void
    {
        ValidationUtil::assertThat(is_array($deserializedRawData));
        /** @var array<mixed, mixed> $deserializedRawData */
        foreach ($deserializedRawData as $key => $value) {
            switch ($key) {
                case 'configured_name':
                    $this->result->nodeConfiguredName = ValidationUtil::assertValidKeywordString($value);
                    break;

                default:
                    throw DataDeserializer::buildException("Unknown key: node->`$key'");
            }
        }
    }
}
