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
final class TextUtil
{
    use StaticClassTrait;

    public static function ensureMaxLength(string $text, int $maxLength): string
    {
        if (strlen($text) <= $maxLength) {
            return $text;
        }
        return substr($text, /* start: */ 0, /* length: */ $maxLength);
    }

    public static function isEmptyString(string $str): bool
    {
        return $str === '';
    }

    public static function isNullOrEmptyString(?string $str): bool
    {
        return $str === null || self::isEmptyString($str);
    }

    public static function isUpperCaseLetter(int $charAsInt): bool
    {
        return NumericUtil::isInClosedInterval(ord('A'), $charAsInt, ord('Z'));
    }

    public static function isLowerCaseLetter(int $charAsInt): bool
    {
        return NumericUtil::isInClosedInterval(ord('a'), $charAsInt, ord('z'));
    }

    public static function isLetter(int $charAsInt): bool
    {
        return self::isUpperCaseLetter($charAsInt) || self::isLowerCaseLetter($charAsInt);
    }

    public static function toLowerCaseLetter(int $charAsInt): int
    {
        if (self::isUpperCaseLetter($charAsInt)) {
            return (($charAsInt - ord('A')) + ord('a'));
        }
        return $charAsInt;
    }

    public static function toUpperCaseLetter(int $charAsInt): int
    {
        if (self::isLowerCaseLetter($charAsInt)) {
            return (($charAsInt - ord('a')) + ord('A'));
        }
        return $charAsInt;
    }

    public static function flipLetterCase(int $charAsInt): int
    {
        return self::isUpperCaseLetter($charAsInt)
            ? self::toLowerCaseLetter($charAsInt)
            : self::toUpperCaseLetter($charAsInt);
    }

    public static function camelToSnakeCase(string $input): string
    {
        $inputLen = strlen($input);
        $result = '';
        $prevIndex = 0;
        for ($i = 0; $i != $inputLen; ++$i) {
            $currentCharAsInt = ord($input[$i]);
            if (!self::isUpperCaseLetter($currentCharAsInt)) {
                continue;
            }
            $result .= substr($input, $prevIndex, $i - $prevIndex);
            if ($i !== 0) {
                $result .= '_';
            }
            $result .= chr(self::toLowerCaseLetter($currentCharAsInt));
            $prevIndex = $i + 1;
        }
        if (self::isEmptyString($result)) {
            return $input;
        }

        $result .= substr($input, $prevIndex, $inputLen - $prevIndex);
        return $result;
    }

    public static function snakeToCamelCase(string $input): string
    {
        $inputLen = strlen($input);
        $result = '';
        /** @var int */
        $inputRemainderPos = 0;
        while (true) {
            $underscorePos = strpos($input, '_', $inputRemainderPos);
            if ($underscorePos === false) {
                break;
            }

            $result .= substr($input, $inputRemainderPos, $underscorePos - $inputRemainderPos);

            $nonUnderscorePos = null;
            for ($i = $underscorePos; $i !== $inputLen; ++$i) {
                if ($input[$i] !== '_') {
                    $nonUnderscorePos = $i;
                    break;
                }
            }

            if ($nonUnderscorePos === null) {
                $inputRemainderPos = strlen($input);
                break;
            }

            // Don't uppercase the first letter
            if (self::isEmptyString($result)) {
                $result .= $input[$nonUnderscorePos];
            } else {
                $result .= chr(self::toUpperCaseLetter(ord($input[$nonUnderscorePos])));
            }
            if ($nonUnderscorePos === $inputRemainderPos - 1) {
                break;
            }
            $inputRemainderPos = $nonUnderscorePos + 1;
        }

        if ($inputRemainderPos === strlen($input)) {
            return $result;
        }

        if (self::isEmptyString($result)) {
            return $input;
        }

        return $result . substr($input, $inputRemainderPos, $inputLen - $inputRemainderPos);
    }

    /**
     * Convert camel case ('someText') to Pascal case ('SomeText')
     *
     * @param string $input
     *
     * @return string
     *
     * @noinspection PhpUnused
     */
    public static function camelToPascalCase(string $input): string
    {
        if (self::isEmptyString($input)) {
            return '';
        }
        return chr(self::toUpperCaseLetter(ord($input[0]))) . substr($input, 1, strlen($input) - 1);
    }

    public static function isPrefixOf(string $prefix, string $text, bool $isCaseSensitive = true): bool
    {
        $prefixLen = strlen($prefix);
        if ($prefixLen === 0) {
            return true;
        }

        /** @noinspection PhpStrictComparisonWithOperandsOfDifferentTypesInspection */
        return substr_compare(
            $text /* <- haystack */,
            $prefix /* <- needle */,
            0 /* <- offset */,
            $prefixLen /* <- length */,
            !$isCaseSensitive /* <- case_insensitivity */
        ) === 0;
    }

    public static function isPrefixOfIgnoreCase(string $prefix, string $text): bool
    {
        return self::isPrefixOf($prefix, $text, /* isCaseSensitive: */ false);
    }

    public static function isSuffixOf(string $suffix, string $text, bool $isCaseSensitive = true): bool
    {
        $suffixLen = strlen($suffix);
        if ($suffixLen === 0) {
            return true;
        }

        /** @noinspection PhpStrictComparisonWithOperandsOfDifferentTypesInspection */
        return substr_compare(
            $text /* <- haystack */,
            $suffix /* <- needle */,
            -$suffixLen /* <- offset */,
            $suffixLen /* <- length */,
            !$isCaseSensitive /* <- case_insensitivity */
        ) === 0;
    }

    public static function contains(string $haystack, string $needle): bool
    {
        return strpos($haystack, $needle) !== false;
    }
}
