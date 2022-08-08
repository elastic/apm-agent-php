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

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class IniRawSnapshotSource implements RawSnapshotSourceInterface
{
    public const DEFAULT_PREFIX = 'elastic_apm.';

    /** @var string */
    private $iniNamesPrefix;

    /**
     * @param string $iniNamesPrefix
     */
    public function __construct(string $iniNamesPrefix)
    {
        $this->iniNamesPrefix = $iniNamesPrefix;
    }

    public static function optionNameToIniName(string $iniNamesPrefix, string $optionName): string
    {
        return $iniNamesPrefix . $optionName;
    }

    public function currentSnapshot(array $optionNameToMeta): RawSnapshotInterface
    {
        /** @var array<string, string> */
        $optionNameToValue = [];

        /** @var array<string, mixed> */
        $allOpts = ini_get_all(/* extension: */ null, /* details */ false);

        foreach ($optionNameToMeta as $optionName => $optionMeta) {
            $iniName = self::optionNameToIniName($this->iniNamesPrefix, $optionName);
            if (($iniValue = ArrayUtil::getValueIfKeyExistsElse($iniName, $allOpts, null)) !== null) {
                $optionNameToValue[$optionName] = self::iniValueToString($iniValue);
            }
        }

        return new RawSnapshotFromArray($optionNameToValue);
    }

    /**
     * @param mixed $iniValue
     *
     * @return string
     */
    private static function iniValueToString($iniValue): string
    {
        if (is_bool($iniValue)) {
            return $iniValue ? 'true' : 'false';
        }

        return strval($iniValue);
    }
}
