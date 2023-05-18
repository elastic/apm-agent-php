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

use Elastic\Apm\Impl\Log\LogCategory;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Log\LoggerFactory;
use Elastic\Apm\Impl\Util\DbgUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class MapPerWeakObjectImplDynamicProperties extends MapPerWeakObject
{
    private const PROPERTY_KEY_PREFIX = 'Elastic_APM_dynamic_property_';

    /** @var Logger */
    private $logger;

    public static function isSupported(): bool
    {
        return PHP_VERSION_ID < 80200;
    }

    public function __construct(LoggerFactory $loggerFactory)
    {
        $this->logger = $loggerFactory->loggerForClass(
            LogCategory::AUTO_INSTRUMENTATION,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        );
    }

    /** @inheritDoc */
    public function set(object $object, string $key, $value): void
    {
        $dynPropName = self::PROPERTY_KEY_PREFIX . $key;
        ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Setting dynamic property...',
            [
                'obj type'    => DbgUtil::getType($object),
                'obj ID'      => spl_object_id($object),
                'key'         => $key,
                'dynPropName' => $dynPropName,
                'value'       => $value,
            ]
        );
        $object->{$dynPropName} = $value;
    }

    /** @inheritDoc */
    public function get(object $object, string $key, &$value): bool
    {
        $dynPropName = self::PROPERTY_KEY_PREFIX . $key;
        $isSet = isset($object->{$dynPropName});
        if ($isSet) {
            $value = $object->{$dynPropName};
        }
        ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Getting dynamic property...',
            [
                'obj type'    => DbgUtil::getType($object),
                'obj ID'      => spl_object_id($object),
                'key'         => $key,
                'dynPropName' => $dynPropName,
                'value'       => $isSet ? $value : 'not set',
            ]
        );
        return $isSet;
    }
}
