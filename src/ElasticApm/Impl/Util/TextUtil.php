<?php

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

    private const INDENTATION = '    '; // 4 spaces

    private const CR_AS_INT = 13;
    private const LF_AS_INT = 10;

    /** @var array<string> */
    private static $endOfLineSequences = ["\r\n", "\r", "\n"];

    /** @var array<string> */
    private static $endOfLineOneCharSequences = ["\r", "\n"];

    public static function containsNewLine(string $text): bool
    {
        return self::containsAnyOf($text, self::$endOfLineOneCharSequences);
    }

    /**
     * @param string        $haystack
     * @param array<string> $needle
     *
     * @return bool
     */
    public static function containsAnyOf(string $haystack, array $needle): bool
    {
        foreach ($needle as $needleChar) {
            if (strpos($haystack, $needleChar) !== false) {
                return true;
            }
        }
        return false;
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

    public static function prefixEachLine(string $text, string $prefix): string
    {
        $result = $prefix;
        $prevPos = 0;
        $currentPos = $prevPos;
        $textLen = strlen($text);
        for (; $currentPos != $textLen;) {
            $endOfLineSeqLength = self::ifEndOfLineSeqGetLength($text, $textLen, $currentPos);
            if ($endOfLineSeqLength === 0) {
                ++$currentPos;
                continue;
            }
            $result .= substr($text, $prevPos, $currentPos + $endOfLineSeqLength - $prevPos);
            $result .= $prefix;
            $prevPos = $currentPos + $endOfLineSeqLength;
            $currentPos = $prevPos;
        }

        $result .= substr($text, $prevPos, $currentPos - $prevPos);

        return $result;
    }

    public static function indent(string $text, int $level = 1): string
    {
        return self::prefixEachLine($text, /* $prefix */ self::indentationForLevel($level));
    }

    private static function indentationForLevel(int $level): string
    {
        return str_repeat(self::INDENTATION, $level);
    }

    public static function ensureMaxLength(string $text, int $maxLength): string
    {
        if (strlen($text) <= $maxLength) {
            return $text;
        }
        return substr($text, /* start: */ 0, /* length: */ $maxLength);
    }

    public static function isEmptyString(string $str): bool
    {
        return strlen($str) === 0;
    }

    public static function isNullOrEmptyString(?string $str): bool
    {
        return is_null($str) || self::isEmptyString($str);
    }

    public static function isUpperCaseLetter(int $charAsInt): bool
    {
        return NumericUtil::isInClosedInterval(ord('A'), $charAsInt, ord('Z'));
    }

    public static function isLowerCaseLetter(int $charAsInt): bool
    {
        return NumericUtil::isInClosedInterval(ord('a'), $charAsInt, ord('z'));
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
        if (empty($result)) {
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

            if (is_null($nonUnderscorePos)) {
                $inputRemainderPos = strlen($input);
                break;
            }

            // Don't uppercase the first letter
            if (empty($result)) {
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

        if (empty($result)) {
            return $input;
        }

        return $result . substr($input, $inputRemainderPos, $inputLen - $inputRemainderPos);
    }

    public static function camelToPascalCase(string $input): string
    {
        if (empty($input)) {
            return '';
        }
        return chr(self::toUpperCaseLetter(ord($input[0]))) . substr($input, 1, strlen($input) - 1);
    }

    public static function isPrefixOf(string $prefix, string $text): bool
    {
        $prefixLen = strlen($prefix);
        if (strlen($text) < $prefixLen) {
            return false;
        }

        return strncmp($text, $prefix, $prefixLen) === 0;
    }

    public static function isSuffixOf(string $suffix, string $text, bool $isCaseSensitive = true): bool
    {
        $suffixLen = strlen($suffix);
        if ($suffixLen === 0) {
            return true;
        }

        return substr_compare(
            $text,
            $suffix, /* offset */
            -$suffixLen,
            $suffixLen, /* case_insensitivity */
            !$isCaseSensitive
        ) === 0;
    }
}
