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

namespace Elastic\Apm\Impl\AutoInstrument\Util;

use Elastic\Apm\Impl\Log\LoggerFactory;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
abstract class MapPerWeakObject
{
    public static function isSupported(): bool
    {
        return MapPerWeakObjectImplWeakMap::isSupported() || MapPerWeakObjectImplDynamicProperties::isSupported();
    }

    public static function create(LoggerFactory $loggerFactory): MapPerWeakObject
    {
        return MapPerWeakObjectImplWeakMap::isSupported()
            ? new MapPerWeakObjectImplWeakMap($loggerFactory)
            : new MapPerWeakObjectImplDynamicProperties($loggerFactory);
    }

    /**
     * @param object $object
     * @param string $key
     * @param mixed  $value
     */
    abstract public function set(object $object, string $key, $value): void;

    /**
     * @param object               $object
     * @param array<string, mixed> $keyValueMap
     */
    public function setMultiple(object $object, array $keyValueMap): void
    {
        foreach ($keyValueMap as $key => $value) {
            $this->set($object, $key, $value);
        }
    }

    /**
     * @param object $object
     * @param string $key
     * @param mixed &$value
     *
     * @return bool
     */
    abstract public function get(object $object, string $key, &$value): bool;

    /**
     * @param object $object
     * @param string $key
     * @param mixed  $defaultValue
     *
     * @return mixed
     */
    public function getOr(object $object, string $key, $defaultValue)
    {
        return $this->get($object, $key, /* out */ $value) ? $value : $defaultValue;
    }

    /**
     * @param object   $object
     * @param string[] $keys
     *
     * @return array<string, mixed>
     */
    public function getMultiple(object $object, array $keys): array
    {
        $keyValueMap = [];
        foreach ($keys as $key) {
            if ($this->get($object, $key, /* out */ $value)) {
                $keyValueMap[$key] = $value;
            }
        }
        return $keyValueMap;
    }
}
