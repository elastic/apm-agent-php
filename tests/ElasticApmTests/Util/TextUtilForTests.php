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

use Elastic\Apm\Impl\Util\RangeUtil;
use Elastic\Apm\Impl\Util\StaticClassTrait;
use Elastic\Apm\Impl\Util\TextUtil;

final class TextUtilForTests
{
    use StaticClassTrait;

    private const CR_AS_INT = 13;
    private const LF_AS_INT = 10;

    /**
     * @param string $input
     *
     * @return iterable<int>
     */
    public static function iterateOverChars(string $input): iterable
    {
        foreach (RangeUtil::generateUpTo(strlen($input)) as $i) {
            yield ord($input[$i]);
        }
    }

    public static function ifEndOfLineSeqGetLength(string $text, int $textLen, int $index): int
    {
        $charAsInt = ord($text[$index]);
        if ($charAsInt === self::CR_AS_INT && $index != ($textLen - 1) && ord($text[$index + 1]) === self::LF_AS_INT) {
            return 2;
        }
        if ($charAsInt === self::CR_AS_INT || $charAsInt === self::LF_AS_INT) {
            return 1;
        }
        return 0;
    }

    /**
     * @param string                       $text
     *
     * @return iterable<string>
     */
    public static function iterateLines(string $text): iterable
    {
        $prevPos = 0;
        $currentPos = $prevPos;
        $textLen = strlen($text);
        for (; $currentPos != $textLen;) {
            $endOfLineSeqLength = self::ifEndOfLineSeqGetLength($text, $textLen, $currentPos);
            if ($endOfLineSeqLength === 0) {
                ++$currentPos;
                continue;
            }
            yield substr($text, $prevPos, $currentPos + $endOfLineSeqLength - $prevPos);
            $prevPos = $currentPos + $endOfLineSeqLength;
            $currentPos = $prevPos;
        }

        yield substr($text, $prevPos, $currentPos - $prevPos);
    }

    public static function prefixEachLine(string $text, string $prefix): string
    {
        $result = '';
        foreach (self::iterateLines($text) as $line) {
            $result .= $prefix . $line;
        }
        return $result;
    }

    public static function contains(string $haystack, string $needle): bool
    {
        return strpos($haystack, $needle) !== false;
    }

    public static function combineWithSeparatorIfNotEmpty(string $separator, string $partToAppend): string
    {
        return (TextUtil::isEmptyString($partToAppend) ? '' : $separator) . $partToAppend;
    }
}
