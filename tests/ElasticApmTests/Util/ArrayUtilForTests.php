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

use Elastic\Apm\Impl\Log\LoggableToString;
use Elastic\Apm\Impl\Util\StaticClassTrait;
use PHPUnit\Framework\Assert;

final class ArrayUtilForTests
{
    use StaticClassTrait;

    /**
     * @template T
     * @param   T[] $array
     * @return  T
     */
    public static function getFirstValue(array $array)
    {
        return $array[array_key_first($array)];
    }

    /**
     * @template T
     * @param   T[] $array
     * @return  T
     */
    public static function getSingleValue(array $array)
    {
        Assert::assertCount(1, $array);
        return self::getFirstValue($array);
    }

    /**
     * @template T
     *
     * @param   T[] $array
     *
     * @return  T
     */
    public static function &getLastValue(array $array)
    {
        return $array[count($array) - 1];
    }

    /**
     * @param string|int           $key
     * @param mixed                $value
     * @param array<string, mixed> $result
     */
    public static function addUnique($key, $value, array &$result): void
    {
        Assert::assertArrayNotHasKey(
            $key,
            $result,
            LoggableToString::convert(['key' => $key, 'value' => $value, 'result' => $result])
        );
        $result[$key] = $value;
    }

    /**
     * @template        T
     * @phpstan-param   iterable<T> $haystack
     * @phpstan-param   callable $predicate
     * @phpstan-param   T $default
     * @phpstan-return  T|null
     *
     * @param iterable $haystack
     * @param callable $predicate
     * @param null     $default
     *
     * @return mixed
     */
    public static function findByPredicate(iterable $haystack, callable $predicate, $default = null)
    {
        foreach ($haystack as $value) {
            if ($predicate($value)) {
                return $value;
            }
        }

        return $default;
    }

    /**
     * @template T
     *
     * @param array<T> $array
     *
     * @return iterable<T>
     */
    public static function iterateListInReverse(array $array): iterable
    {
        $arrayCount = count($array);
        for ($i = $arrayCount - 1; $i >= 0; --$i) {
            yield $array[$i];
        }
    }

    /**
     * @template TKey of string|int
     * @template TValue
     *
     * @param array<TKey, TValue> $from
     * @param array<TKey, TValue> $to
     */
    public static function append(array $from, array &$to): void
    {
        $to = array_merge($to, $from);
    }

    /**
     * @template TKey
     * @template TValue
     *
     * @param array<TKey, TValue> $map
     * @param TKey                $keyToFind
     *
     * @return  int
     */
    public static function getAdditionOrderIndex(array $map, $keyToFind): int
    {
        $additionOrderIndex = 0;
        foreach ($map as $key => $ignored) {
            if ($key === $keyToFind) {
                return $additionOrderIndex;
            }
            ++$additionOrderIndex;
        }
        Assert::fail('Not found key in map; ' . LoggableToString::convert(['keyToFind' => $keyToFind, 'map' => $map]));
    }
}
