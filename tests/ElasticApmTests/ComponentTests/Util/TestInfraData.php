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

namespace ElasticApmTests\ComponentTests\Util;

use Elastic\Apm\Impl\BackendComm\SerializationUtil;
use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableTrait;
use Elastic\Apm\Impl\Util\JsonUtil;
use JsonSerializable;

abstract class TestInfraData implements JsonSerializable, LoggableInterface
{
    use LoggableTrait;

    /**
     * @return array<string, mixed>
     *
     * Called by json_encode
     * @noinspection PhpUnused
     */
    public function jsonSerialize(): array
    {
        $result = [];

        // @phpstan-ignore-next-line - see https://github.com/phpstan/phpstan/issues/1060
        foreach ($this as $thisObjPropName => $thisObjPropValue) {
            $result[$thisObjPropName] = $thisObjPropValue;
        }

        return $result;
    }

    public function serializeToString(): string
    {
        return SerializationUtil::serializeAsJson($this);
    }

    /**
     * @param array<string, mixed> $decodedJson
     *
     * @return static
     */
    public static function deserializeFromJson(array $decodedJson): self
    {
        $result = new static(); // @phpstan-ignore-line

        foreach ($decodedJson as $jsonKey => $jsonVal) {
            $result->$jsonKey = $jsonVal;
        }

        return $result;
    }

    /**
     * @param string $encodedJson
     *
     * @return static
     */
    public static function deserializeFromString(string $encodedJson): self
    {
        $decodedJson = JsonUtil::decode($encodedJson, /* asAssocArray */ true);
        /** @var array<string, mixed> $decodedJson */
        return static::deserializeFromJson($decodedJson);
    }
}
