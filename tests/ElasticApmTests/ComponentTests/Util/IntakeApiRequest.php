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

use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableTrait;
use Elastic\Apm\Impl\Util\ClassNameUtil;
use JsonSerializable;
use RuntimeException;

final class IntakeApiRequest implements JsonSerializable, LoggableInterface
{
    use LoggableTrait;

    /** @var array<string, array<string>> */
    public $headers;

    /** @var string */
    public $body;

    /** @var float */
    public $timeReceivedAtServer;

    /**
     * @return array<string, mixed>
     *
     * Called by json_encode
     * @noinspection PhpUnused
     */
    public function jsonSerialize(): array
    {
        $result = [];

        foreach (get_object_vars($this) as $thisObjPropName => $thisObjPropValue) {
            $result[$thisObjPropName] = $thisObjPropValue;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $decodedJson
     */
    public static function jsonDeserialize(array $decodedJson): self
    {
        $thisObj = new self();

        foreach ($decodedJson as $propName => $propValue) {
            if (!property_exists($thisObj, $propName)) {
                throw new RuntimeException(
                    'Unexpected key `' . $propName . '\' - there is no corresponding property in '
                    . ClassNameUtil::fqToShort(get_class($thisObj)) . ' class'
                );
            }
            $thisObj->$propName = $propValue;
        }

        return $thisObj;
    }
}
