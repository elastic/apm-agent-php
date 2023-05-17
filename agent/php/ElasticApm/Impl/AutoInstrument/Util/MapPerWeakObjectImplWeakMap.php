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

/** @noinspection PhpElementIsNotAvailableInCurrentPhpVersionInspection */

declare(strict_types=1);

namespace Elastic\Apm\Impl\AutoInstrument\Util;

use Elastic\Apm\Impl\Log\LogCategory;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Log\LoggerFactory;
use Elastic\Apm\Impl\Util\DbgUtil;
use WeakMap;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class MapPerWeakObjectImplWeakMap extends MapPerWeakObject
{
    /** @var Logger */
    private $logger;

    /** @var WeakMap<object, array<string, mixed>> */
    private $weakMap;

    public static function isSupported(): bool
    {
        return PHP_MAJOR_VERSION >= 8;
    }

    public function __construct(LoggerFactory $loggerFactory)
    {
        $this->logger = $loggerFactory->loggerForClass(
            LogCategory::AUTO_INSTRUMENTATION,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        );

        $this->weakMap = new WeakMap();
    }

    /** @inheritDoc */
    public function set(object $object, string $key, $value): void
    {
        ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Adding to weak map...',
            [
                'obj type' => DbgUtil::getType($object),
                'obj ID'   => spl_object_id($object),
                'key'      => $key,
                'value'    => $value,
            ]
        );

        if (!$this->weakMap->offsetExists($object)) {
            $this->weakMap[$object] = [];
        }
        $this->weakMap[$object][$key] = $value;
    }

    /** @inheritDoc */
    public function get(object $object, string $key, &$value): bool
    {
        if ($this->weakMap->offsetExists($object)) {
            /** @var array<string, mixed> $keyValueMap */
            $keyValueMap = $this->weakMap[$object];
            $isSet = array_key_exists($key, $keyValueMap);
            if ($isSet) {
                $value = $keyValueMap[$key];
            }
        } else {
            $isSet = false;
        }

        ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Getting from weak map...',
            [
                'obj type'      => DbgUtil::getType($object),
                'obj ID'        => spl_object_id($object),
                'key'           => $key,
                'value'         => $isSet ? $value : 'not set',
            ]
        );
        return $isSet;
    }
}
