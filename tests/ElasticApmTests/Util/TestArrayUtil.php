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

use Elastic\Apm\Impl\Util\StaticClassTrait;

final class TestArrayUtil
{
    use StaticClassTrait;

    /**
     * @param array<mixed>  $array
     *
     * @return mixed
     *
     * @template        T
     * @phpstan-param   T[] $array
     * @phpstan-return  T
     */
    public static function getFirstValue(array $array)
    {
        return $array[array_key_first($array)];
    }

    /**
     * @param array<mixed>  $array
     *
     * @return mixed
     *
     * @template        T
     * @phpstan-param   T[] $array
     * @phpstan-return  T
     */
    public static function getLastValue(array $array)
    {
        return $array[count($array) - 1];
    }
}
