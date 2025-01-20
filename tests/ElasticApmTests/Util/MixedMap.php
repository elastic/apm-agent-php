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

use ArrayAccess;
use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LogStreamInterface;
use Elastic\Apm\Impl\Util\ArrayUtil;
use Elastic\Apm\Impl\Util\DbgUtil;

/**
 * @implements ArrayAccess<string, mixed>
 */
class MixedMap implements LoggableInterface, ArrayAccess
{
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
     * @param array<mixed> $array
     *
     * @return array<string, mixed>
     */
    public static function assertValidMixedMapArray(array $array): array
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, AssertMessageStack::funcArgs());

        foreach ($array as $key => $ignored) {
            TestCaseBase::assertIsString($key);
        }
        /**
         * @var array<string, mixed> $array
         * @noinspection PhpRedundantVariableDocTypeInspection
         */
        return $array;
    }

    /**
     * @param string       $key
     * @param array<mixed> $from
     *
     * @return mixed
     */
    public static function getFrom(string $key, array $from)
    {
        TestCaseBase::assertArrayHasKey($key, $from);
        return $from[$key];
    }

    /**
     * @param string $key
     *
     * @return mixed
     */
    public function get(string $key)
    {
        return self::getFrom($key, $this->map);
    }

    /**
     * @param string $key
     * @param mixed  $fallbackValue
     *
     * @return mixed
     */
    public function getIfKeyExistsElse(string $key, $fallbackValue)
    {
        return ArrayUtil::getValueIfKeyExistsElse($key, $this->map, $fallbackValue);
    }

    /**
     * @param string       $key
     * @param array<mixed> $from
     *
     * @return bool
     */
    public static function getBoolFrom(string $key, array $from): bool
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, AssertMessageStack::funcArgs());
        $value = self::getFrom($key, $from);
        TestCaseBase::assertIsBool($value);
        return $value;
    }

    public function getBool(string $key): bool
    {
        return self::getBoolFrom($key, $this->map);
    }

    /**
     * @param string       $key
     * @param array<mixed> $from
     *
     * @return ?string
     */
    public static function getNullableStringFrom(string $key, array $from): ?string
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, AssertMessageStack::funcArgs());
        $dbgCtx->add(['from' => $from]);
        $value = self::getFrom($key, $from);
        if ($value !== null) {
            TestCaseBase::assertIsString($value);
        }
        return $value;
    }

    public function getNullableString(string $key): ?string
    {
        return self::getNullableStringFrom($key, $this->map);
    }

    public function getString(string $key): string
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, array_merge(['this' => $this], AssertMessageStack::funcArgs()));
        $value = $this->getNullableString($key);
        TestCaseBase::assertNotNull($value);
        return $value;
    }

    public function getNullableFloat(string $key): ?float
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, array_merge(['this' => $this], AssertMessageStack::funcArgs()));
        $value = $this->get($key);
        if ($value === null || is_float($value)) {
            return $value;
        }
        if (is_int($value)) {
            return floatval($value);
        }
        $dbgCtx->add(['value type' => DbgUtil::getType($value), 'value' => $value]);
        TestCaseBase::fail('Value is not a float');
    }

    /** @noinspection PhpUnused */
    public function getFloat(string $key): float
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, array_merge(['this' => $this], AssertMessageStack::funcArgs()));
        $value = $this->getNullableFloat($key);
        TestCaseBase::assertNotNull($value);
        return $value;
    }

    public function getNullableInt(string $key): ?int
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, array_merge(['this' => $this], AssertMessageStack::funcArgs()));
        $value = $this->get($key);
        if ($value === null || is_int($value)) {
            return $value;
        }

        $dbgCtx->add(['value type' => DbgUtil::getType($value), 'value' => $value]);
        TestCaseBase::fail('Value is not a int');
    }

    /**
     * @param string $key
     *
     * @return null|positive-int|0
     */
    public function getNullablePositiveOrZeroInt(string $key): ?int
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, array_merge(['this' => $this], AssertMessageStack::funcArgs()));
        $value = $this->getNullableInt($key);
        if ($value !== null) {
            TestCaseBase::assertGreaterThanOrEqual(0, $value);
        }
        /** @var null|positive-int|0 $value */
        $dbgCtx->pop();
        return $value;
    }

    public function getInt(string $key): int
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, array_merge(['this' => $this], AssertMessageStack::funcArgs()));
        $value = $this->getNullableInt($key);
        TestCaseBase::assertNotNull($value);
        return $value;
    }

    /**
     * @param string $key
     *
     * @return positive-int|0
     */
    public function getPositiveOrZeroInt(string $key): int
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, array_merge(['this' => $this], AssertMessageStack::funcArgs()));
        $value = $this->getInt($key);
        TestCaseBase::assertGreaterThanOrEqual(0, $value);
        /** @var positive-int|0 $value */
        $dbgCtx->pop();
        return $value;
    }

    /**
     * @param string $key
     *
     * @return ?array<mixed>
     */
    public function getNullableArray(string $key): ?array
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, array_merge(['this' => $this], AssertMessageStack::funcArgs()));
        $value = $this->get($key);
        if ($value !== null) {
            TestCaseBase::assertIsArray($value);
        }
        return $value;
    }

    /**
     * @param string $key
     *
     * @return array<mixed>
     */
    public function getArray(string $key): array
    {
        $value = $this->getNullableArray($key);
        TestCaseBase::assertNotNull($value);
        return $value;
    }

    /**
     * @return self
     */
    public function clone(): self
    {
        return new MixedMap($this->map);
    }

    /**
     * @return array<string, mixed>
     */
    public function cloneAsArray(): array
    {
        return $this->map;
    }

    /**
     * @inheritDoc
     *
     * @param string $offset
     *
     * @return bool
     */
    public function offsetExists($offset): bool
    {
        return array_key_exists($offset, $this->map);
    }

    /**
     * @inheritDoc
     *
     * @param string $offset
     *
     * @return mixed
     *
     * @noinspection PhpFullyQualifiedNameUsageInspection
     * @noinspection PhpLanguageLevelInspection
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->map[$offset];
    }

    /**
     * @inheritDoc
     *
     * @param string $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value): void
    {
        TestCaseBase::assertNotNull($offset);
        $this->map[$offset] = $value;
    }

    /**
     * @inheritDoc
     *
     * @param string $offset
     */
    public function offsetUnset($offset): void
    {
        TestCaseBase::assertArrayHasKey($offset, $this->map);
        unset($this->map[$offset]);
    }

    public function toLog(LogStreamInterface $stream): void
    {
        $stream->toLogAs($this->map);
    }
}
