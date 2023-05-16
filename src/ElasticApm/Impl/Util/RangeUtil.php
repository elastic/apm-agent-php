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
final class RangeUtil
{
    use StaticClassTrait;

    /**
     * @param int $begin
     * @param int $end
     * @param int $step
     *
     * @return iterable<int>
     */
    public static function generate(int $begin, int $end, int $step = 1): iterable
    {
        for ($i = $begin; $i < $end; $i += $step) {
            yield $i;
        }
    }

    /**
     * @param int $begin
     * @param int $end
     * @param int $step
     *
     * @return iterable<int>
     */
    public static function generateDown(int $begin, int $end, int $step = 1): iterable
    {
        for ($i = $begin; $i > $end; $i -= $step) {
            yield $i;
        }
    }

    /**
     * @param int $count
     *
     * @return iterable<int>
     */
    public static function generateUpTo(int $count): iterable
    {
        return self::generate(0, $count);
    }

    /**
     * @param int $count
     *
     * @return iterable<int>
     */
    public static function generateDownFrom(int $count): iterable
    {
        for ($i = $count - 1; $i >= 0; --$i) {
            yield $i;
        }
    }

    /**
     * @param int $first
     * @param int $last
     *
     * @return iterable<int>
     */
    public static function generateFromToIncluding(int $first, int $last): iterable
    {
        return self::generate($first, $last + 1);
    }
}
