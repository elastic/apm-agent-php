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
final class IdGenerator
{
    use StaticClassTrait;

    /** @var int */
    public const TRACE_ID_SIZE_IN_BYTES = 16;

    public static function generateId(int $idLengthInBytes): string
    {
        return self::convertBinaryIdToString(self::generateBinaryId($idLengthInBytes));
    }

    /**
     * @param array<int> $binaryId
     *
     * @return string
     */
    private static function convertBinaryIdToString(array $binaryId): string
    {
        $result = '';
        for ($i = 0; $i < count($binaryId); ++$i) {
            $result .= sprintf('%02x', $binaryId[$i]);
        }
        return $result;
    }

    /**
     * @param int $idLengthInBytes
     *
     * @return array<int>
     */
    private static function generateBinaryId(int $idLengthInBytes): array
    {
        $result = [];
        for ($i = 0; $i < $idLengthInBytes; ++$i) {
            $result[] = mt_rand(0, 255);
        }
        return $result;
    }
}
