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

use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableTrait;

class ExpectationsBase implements LoggableInterface
{
    use LoggableTrait;

    public function isEmpty(): bool
    {
        return self::isEmptyImpl($this);
    }

    /**
     * @param mixed $val
     *
     * @return bool
     */
    private static function isEmptyImpl($val): bool
    {
        if ($val === null) {
            return true;
        }

        if ($val instanceof Optional) {
            if (!$val->isValueSet()) {
                return true;
            }
            return self::isEmptyImpl($val->getValue());
        }

        if (is_object($val)) {
            foreach (get_object_vars($val) as $propVal) {
                if (!self::isEmptyImpl($propVal)) {
                    return false;
                }
            }
        }

        return true;
    }

    protected static function setCommonProperties(object $src, object $dst): int
    {
        $count = 0;
        foreach (get_object_vars($src) as $propName => $propValue) {
            if (!property_exists($dst, $propName)) {
                continue;
            }
            $dst->$propName = $propValue;
            ++$count;
        }
        return $count;
    }
}
