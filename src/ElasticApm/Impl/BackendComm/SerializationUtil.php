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

namespace Elastic\Apm\Impl\BackendComm;

use Elastic\Apm\Impl\ContextDataInterface;
use Elastic\Apm\Impl\Util\ExceptionUtil;
use Elastic\Apm\Impl\Util\JsonUtil;
use Elastic\Apm\Impl\Util\StaticClassTrait;
use Exception;
use JsonSerializable;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class SerializationUtil
{
    use StaticClassTrait;

    /** @var bool */
    public static $isInTestingContext = false;

    /**
     * @param mixed $data
     *
     * @return string
     */
    public static function serializeAsJson($data): string
    {
        try {
            $serializedData = JsonUtil::encode($data);
        } catch (Exception $ex) {
            throw new SerializationException(
                ExceptionUtil::buildMessage('Serialization failed', ['data' => $data]),
                $ex
            );
        }
        return $serializedData;
    }

    /**
     * @param string                                              $name
     * @param bool|int|float|string|JsonSerializable|array<mixed> $value
     * @param array<string, mixed>                                $nameToValue
     *
     * @return void
     */
    public static function addNameValue(string $name, $value, array &$nameToValue): void
    {
        if (self::$isInTestingContext && array_key_exists($name, $nameToValue)) {
            throw new SerializationException(
                ExceptionUtil::buildMessage(
                    'Given key already exists in given array',
                    ['name' => $name, 'value' => $value, 'nameToValue' => $nameToValue]
                )
            );
        }

        $nameToValue[$name] = $value;
    }

    /**
     * @param string                                                   $name
     * @param null|bool|int|float|string|JsonSerializable|array<mixed> $value
     * @param array<string, mixed>                                     $nameToValue
     *
     * @return void
     */
    public static function addNameValueIfNotNull(string $name, $value, array &$nameToValue): void
    {
        if ($value === null) {
            return;
        }

        self::addNameValue($name, $value, /* ref */ $nameToValue);
    }

    public static function isNullOrEmpty(?ContextDataInterface $value): bool
    {
        return ($value === null) || $value->isEmpty();
    }

    /**
     * @param string                    $name
     * @param ContextDataInterface|null $value
     * @param array<string, mixed>      $nameToValue
     *
     * @return void
     */
    public static function addNameValueIfNotNullOrEmpty(
        string $name,
        ?ContextDataInterface $value,
        array &$nameToValue
    ): void {
        if (self::isNullOrEmpty($value)) {
            return;
        }
        /** @phpstan-var ContextDataInterface $value */

        self::addNameValue($name, $value, /* ref */ $nameToValue);
    }

    /**
     * @param string               $name
     * @param array<mixed, mixed>  $value
     * @param array<string, mixed> $nameToValue
     *
     * @return void
     */
    public static function addNameValueIfNotEmpty(string $name, array $value, array &$nameToValue): void
    {
        if (empty($value)) {
            return;
        }

        self::addNameValue($name, $value, /* ref */ $nameToValue);
    }

    /**
     * @param float $timestamp
     *
     * @return float|int
     */
    public static function adaptTimestamp(float $timestamp)
    {
        // If int type is large enough to hold 64-bit (8 bytes) use it instead of float
        return (PHP_INT_SIZE >= 8) ? intval($timestamp) : $timestamp;
    }
}
