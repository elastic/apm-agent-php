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

namespace Elastic\Apm\Impl\Config;

use Elastic\Apm\Impl\Util\ArrayUtil;
use Elastic\Apm\Impl\Util\TextUtil;
use RuntimeException;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
trait SnapshotTrait
{
    /** @var array<string, mixed> */
    private $optNameToParsedValue;

    /**
     * @param array<string, mixed> $optNameToParsedValue
     */
    protected function setPropertiesToValuesFrom(array $optNameToParsedValue): void
    {
        $this->optNameToParsedValue = $optNameToParsedValue;

        foreach ($optNameToParsedValue as $optName => $parsedValue) {
            $propertyName = TextUtil::snakeToCamelCase($optName);
            $actualClass = get_called_class();
            if (!property_exists($actualClass, $propertyName)) {
                throw new RuntimeException("Property `$propertyName' doesn't exist in class " . $actualClass);
            }
            $this->$propertyName = $parsedValue;
        }

        $this->optNameToParsedValue = $optNameToParsedValue;
    }

    /**
     * @param string $optName
     *
     * @return mixed
     */
    public function getOptionValueByName(string $optName)
    {
        return ArrayUtil::getValueIfKeyExistsElse($optName, $this->optNameToParsedValue, null);
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptionNameToParsedValueMap(): array
    {
        return $this->optNameToParsedValue;
    }
}
