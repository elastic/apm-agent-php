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
final class RandomUtil
{
    use StaticClassTrait;

    private const MAX_INT_FOR_01_FLOAT = 0x7FFFFFFF;

    public static function generate01Float(): float
    {
        try {
            return random_int(0, self::MAX_INT_FOR_01_FLOAT) / self::MAX_INT_FOR_01_FLOAT;
        } catch (\Throwable $ex) {
            // random_int() throws if no OS entropy source is available (for example getrandom() blocked by seccomp).
            // The agent must never break the host application so fall back to mt_rand().
            return mt_rand(0, mt_getrandmax()) / mt_getrandmax();
        }
    }
}
