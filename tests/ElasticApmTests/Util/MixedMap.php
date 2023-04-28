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

namespace ElasticApmTests\Util;

use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableTrait;
use Elastic\Apm\Impl\Util\DbgUtil;
use PHPUnit\Framework\Assert;

class MixedMap implements LoggableInterface
{
    use LoggableTrait;

    /** @var array<string, mixed> */
    private $map;

    /**
     * @param array<string, mixed> $initialMap
     */
    public function __construct(array $initialMap)
    {
        $this->map = $initialMap;
    }

    /**
     * @param string $key
     *
     * @return mixed
     */
    public function get(string $key)
    {
        Assert::assertArrayHasKey($key, $this->map);
        return $this->map[$key];
    }

    public function getBool(string $key): bool
    {
        $value = $this->get($key);
        Assert::assertIsBool($value, AssertMessageBuilder::buildString(['key' => $key, 'this' => $this]));
        return $value;
    }

    public function getInt(string $key): int
    {
        $value = $this->get($key);
        Assert::assertIsInt($value, AssertMessageBuilder::buildString(['key' => $key, 'this' => $this]));
        return $value;
    }

    public function getNullableString(string $key): ?string
    {
        $value = $this->get($key);
        if ($value !== null) {
            Assert::assertIsString($value, AssertMessageBuilder::buildString(['key' => $key, 'this' => $this]));
        }
        return $value;
    }

    public function getString(string $key): string
    {
        $value = $this->getNullableString($key);
        Assert::assertNotNull($value, AssertMessageBuilder::buildString(['key' => $key, 'this' => $this]));
        return $value;
    }

    public function getNullableFloat(string $key): ?float
    {
        $value = $this->get($key);
        if ($value === null || is_float($value)) {
            return $value;
        }
        if (is_int($value)) {
            return floatval($value);
        }
        Assert::assertIsString($value, AssertMessageBuilder::buildString(['key' => $key, 'this' => $this]));
        Assert::fail('Value is not a float' . AssertMessageBuilder::buildString(['value type' => DbgUtil::getType($value), 'value' => $value, 'key' => $key, 'this' => $this]));
    }

    public function getFloat(string $key): float
    {
        $value = $this->getNullableFloat($key);
        Assert::assertNotNull($value, AssertMessageBuilder::buildString(['key' => $key, 'this' => $this]));
        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function asArray(): array
    {
        return $this->map;
    }
}
