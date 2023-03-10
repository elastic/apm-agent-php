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

namespace Elastic\Apm\Impl\Util;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class ArrayUtil
{
    use StaticClassTrait;

    /**
     * @param string        $key
     * @param array<mixed>  $array
     * @param mixed         $valueDst
     *
     * @return bool
     *
     * @template        T
     * @phpstan-param   T[] $array
     * @phpstan-param   T   $valueDst
     */
    public static function getValueIfKeyExists(string $key, array $array, &$valueDst): bool
    {
        if (!array_key_exists($key, $array)) {
            return false;
        }

        $valueDst = $array[$key];
        return true;
    }

    /**
     * @param string|int               $key
     * @param array<string|int, mixed> $array
     * @param mixed                    $fallbackValue
     *
     * @return mixed
     *
     * @template        T
     * @phpstan-param   T[] $array
     * @phpstan-param   T   $fallbackValue
     * @phpstan-return  T
     */
    public static function getValueIfKeyExistsElse($key, array $array, $fallbackValue)
    {
        return array_key_exists($key, $array) ? $array[$key] : $fallbackValue;
    }

    /**
     * @param string|int               $key
     * @param array<string|int, mixed> $array
     * @param string                   $fallbackValue
     *
     * @return string
     */
    public static function getStringValueIfKeyExistsElse($key, array $array, string $fallbackValue): string
    {
        if (!array_key_exists($key, $array)) {
            return $fallbackValue;
        }

        $value = $array[$key];

        if (!is_string($value)) {
            return $fallbackValue;
        }

        return $value;
    }

    /**
     * @param string|int               $key
     * @param array<string|int, mixed> $array
     * @param ?string                  $fallbackValue
     *
     * @return ?string
     */
    public static function getNullableStringValueIfKeyExistsElse($key, array $array, ?string $fallbackValue): ?string
    {
        if (!array_key_exists($key, $array)) {
            return $fallbackValue;
        }

        $value = $array[$key];

        if (!is_string($value)) {
            return $fallbackValue;
        }

        return $value;
    }

    /**
     * @param string|int               $key
     * @param array<string|int, mixed> $array
     * @param int                      $fallbackValue
     *
     * @return int
     */
    public static function getIntValueIfKeyExistsElse($key, array $array, int $fallbackValue): int
    {
        if (!array_key_exists($key, $array)) {
            return $fallbackValue;
        }

        $value = $array[$key];

        if (!is_int($value)) {
            return $fallbackValue;
        }

        return $value;
    }

    /**
     * @param string|int               $key
     * @param array<string|int, mixed> $array
     * @param ?int                     $fallbackValue
     *
     * @return ?int
     */
    public static function getNullableIntValueIfKeyExistsElse($key, array $array, ?int $fallbackValue): ?int
    {
        if (!array_key_exists($key, $array)) {
            return $fallbackValue;
        }

        $value = $array[$key];

        if (!is_int($value)) {
            return $fallbackValue;
        }

        return $value;
    }

    /**
     * @template TKey of string|int
     * @template TValue
     *
     * @param TKey                $key
     * @param TValue              $defaultValue
     * @param array<TKey, TValue> $array
     *
     * @return TValue
     */
    public static function &getOrAdd($key, $defaultValue, array &$array)
    {
        if (!array_key_exists($key, $array)) {
            $array[$key] = $defaultValue;
        }

        return $array[$key];
    }

    /**
     * @param array<mixed, mixed> $array
     *
     * @return bool
     */
    public static function isEmpty(array $array): bool
    {
        return count($array) === 0;
    }

    /**
     * @param array<mixed, mixed> $array
     *
     * @return bool
     */
    public static function isList(array $array): bool
    {
        $expectedKey = 0;
        foreach ($array as $key => $_) {
            if ($key !== $expectedKey) {
                return false;
            }
            ++$expectedKey;
        }
        return true;
    }

    /**
     * @param array<string, mixed> $srcArray
     * @param string               $key
     * @param array<string, mixed> $dstArray
     */
    public static function copyByArrayKeyIfExists(array $srcArray, string $key, array &$dstArray): void
    {
        if (array_key_exists($key, $srcArray)) {
            $dstArray[$key] = $srcArray[$key];
        }
    }

    /**
     * @param string               $key
     * @param array<string, mixed> $array
     */
    public static function removeKeyIfExists(string $key, array &$array): void
    {
        if (array_key_exists($key, $array)) {
            unset($array[$key]);
        }
    }
}
