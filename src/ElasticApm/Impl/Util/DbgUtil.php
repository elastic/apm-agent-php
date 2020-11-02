<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Util;

use Throwable;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class DbgUtil
{
    use StaticClassTrait;

    public const NULL_AS_STRING = 'null';

    public static function getCallerInfoFromStacktrace(int $numberOfStackFramesToSkip): CallerInfo
    {
        $callerStackFrameIndex = $numberOfStackFramesToSkip + 1;
        $stackFrames = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, /* limit: */ $callerStackFrameIndex + 1);

        ($assertProxy = Assert::ifEnabled())
        && $assertProxy->that(count($stackFrames) >= $callerStackFrameIndex + 1)
        && $assertProxy->withContext('count($stackFrames) >= $callerStackFrameIndex + 1', []);

        $stackFrame = $stackFrames[$callerStackFrameIndex];
        return new CallerInfo(
            ArrayUtil::getValueIfKeyExistsElse('file', $stackFrame, null),
            ArrayUtil::getValueIfKeyExistsElse('line', $stackFrame, null),
            ArrayUtil::getValueIfKeyExistsElse('class', $stackFrame, null),
            ArrayUtil::getValueIfKeyExistsElse('function', $stackFrame, null)
        );
    }

    /**
     * @param mixed $value
     *
     * @return string
     */
    public static function getType($value): string
    {
        if (is_object($value)) {
            return get_class($value);
        }
        return gettype($value);
    }

    /**
     * @param mixed $value
     *
     * @return string
     */
    public static function formatValue($value): string
    {
        try {
            return self::formatValueImpl($value);
        } catch (Throwable $thrown) {
            return '<' . 'FAILED to format value of type ' . self::getType($value)
                   . ': ' . self::formatThrowable($thrown) . '>';
        }
    }

    /**
     * @param mixed $value
     *
     * @return string
     */
    private static function formatValueImpl($value): string
    {
        if (is_null($value)) {
            return self::NULL_AS_STRING;
        }

        if (is_bool($value)) {
            return self::formatBool($value);
        }

        if (is_string($value)) {
            $jsonEncodedString = json_encode($value);
            if ($jsonEncodedString === false) {
                return '"FAILED to encode string"';
            }
            return $jsonEncodedString;
        }

        if (is_numeric($value)) {
            return strval($value);
        }

        if (is_array($value)) {
            return self::formatArray($value);
        }

        if (is_object($value)) {
            return self::formatObject($value);
        }

        return '"<' . self::getType($value) . '> ' . strval($value) . '"';
    }

    private static function formatBool(bool $value): string
    {
        return $value ? 'true' : 'false';
    }

    /**
     * @param array<mixed> $arrayValue
     *
     * @return string
     */
    public static function formatArray(array $arrayValue): string
    {
        return self::isListArray($arrayValue) ? self::formatListArray($arrayValue) : self::formatMapArray($arrayValue);
    }

    /**
     * @param array<mixed> $arrayValue
     *
     * @return string
     */
    public static function formatMapArray(array $arrayValue): string
    {
        $result = '{';
        $result .= '"type": "map-array", "count": ' . count($arrayValue) . ', "keyValuePairs": [';
        $isFirst = true;
        foreach ($arrayValue as $key => $value) {
            if ($isFirst) {
                $isFirst = false;
            } else {
                $result .= ', ';
            }
            $result .= '{"key": ' . self::formatValue($key) . ', "value": ' . self::formatValue($value) . '}';
        }
        $result .= ']}';
        return $result;
    }

    /**
     * @param array<mixed> $arrayValue
     *
     * @return string
     */
    public static function formatListArray(array $arrayValue): string
    {
        $result = '{';
        $result .= '"type": "list-array", "count": ' . count($arrayValue) . ', "values": [';
        $isFirst = true;
        foreach ($arrayValue as $value) {
            if ($isFirst) {
                $isFirst = false;
            } else {
                $result .= ', ';
            }
            $result .= self::formatValue($value);
        }
        $result .= ']}';
        return $result;
    }

    /**
     * @param array<mixed, mixed> $arrayValue
     *
     * @return bool
     */
    public static function isListArray(array $arrayValue): bool
    {
        $expectedKey = 0;
        foreach ($arrayValue as $key => $value) {
            if ($key !== $expectedKey++) {
                return false;
            }
        }
        return true;
    }

    private static function formatObject(object $objectValue): string
    {
        if ($objectValue instanceof Throwable) {
            return self::formatThrowable($objectValue);
        }

        if (method_exists($objectValue, '__debugInfo')) {
            return self::formatValue($objectValue->__debugInfo());
        }

        if (method_exists($objectValue, '__toString')) {
            return $objectValue->__toString();
        }

        return self::getType($objectValue);
    }

    private static function formatThrowable(Throwable $throwable): string
    {
        $throwableAsString = $throwable->__toString();
        $throwableClassName = get_class($throwable);
        if (TextUtil::isPrefixOf($throwableClassName, $throwableAsString)) {
            return $throwableAsString;
        }

        return "$throwableClassName: $throwableAsString";
    }

    /**
     * @param array<mixed> $stackFrame
     *
     * @return string
     */
    private static function formatStackFrame(array $stackFrame): string
    {
        $result = '';
        if (!is_null($className = ArrayUtil::getValueIfKeyExistsElse('class', $stackFrame, null))) {
            $result .= $className;
            $result .= '::';
        }

        $result .= ArrayUtil::getValueIfKeyExistsElse('function', $stackFrame, /** @lang text */ '<UNKNOWN FUNCTION>');

        $result .= ' called at [';
        if (!is_null($srcFile = ArrayUtil::getValueIfKeyExistsElse('file', $stackFrame, null))) {
            $result .= self::formatSourceCodeFilePath($srcFile);
        } else {
            $result
                .= /** @lang text */
                '<UNKNOWN FILE>';
        }
        $result .= ':';
        $result .= ArrayUtil::getValueIfKeyExistsElse('line', $stackFrame, /** @lang text */ '<UNKNOWN LINE>');
        $result .= ']';

        if (!is_null($callThisObj = ArrayUtil::getValueIfKeyExistsElse('object', $stackFrame, null))) {
            $result .= ' | this: ';
            $result .= self::formatValue($callThisObj);
        }

        if (!is_null($callArgs = ArrayUtil::getValueIfKeyExistsElse('args', $stackFrame, null))) {
            $argIndex = 1;
            foreach ($callArgs as $argValue) {
                if ($argIndex === 1) {
                    $result .= ' | args[';
                    $result .= count($callArgs);
                    $result .= ']: ';
                } else {
                    $result .= ', ';
                }
                $result .= "arg$argIndex: ";
                $result .= self::formatValue($argValue);
                ++$argIndex;
            }
        }

        return $result;
    }

    public static function formatSourceCodeFilePath(string $srcFile): string
    {
        return basename($srcFile);
    }

    public static function formatCurrentStackTrace(int $numberOfStackFramesToSkip): string
    {
        // #0  c() called at [/tmp/include.php:10]
        // #1  b() called at [/tmp/include.php:6]
        // #2  a() called at [/tmp/include.php:17]

        $actualNumberOfStackFramesToSkip = $numberOfStackFramesToSkip + 1;
        $result = '';
        $stackFrames = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT);
        $index = 0;
        foreach ($stackFrames as $stackFrame) {
            if ($index >= $actualNumberOfStackFramesToSkip) {
                if ($index != $actualNumberOfStackFramesToSkip) {
                    $result .= PHP_EOL;
                }
                $result .= '#';
                $result .= ($index - $actualNumberOfStackFramesToSkip);
                $result .= '  ';
                $result .= self::formatStackFrame($stackFrame);
            }
            ++$index;
        }
        return $result;
    }

    public static function fqToShortClassName(string $fqClassName): string
    {
        $forwardSlashPos = strrchr($fqClassName, '\\');
        if ($forwardSlashPos === false) {
            return $fqClassName;
        }
        return substr($forwardSlashPos, 1);
    }
}
