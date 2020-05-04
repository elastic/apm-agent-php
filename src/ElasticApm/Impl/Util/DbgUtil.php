<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Util;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class DbgUtil
{
    use StaticClassTrait;

    public static function getCallerInfoFromStacktrace(int $numberOfStackFramesToSkip): CallerInfo
    {
        $callerStackFrameIndex = $numberOfStackFramesToSkip + 1;
        $stackFrames = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, /* limit: */ $callerStackFrameIndex + 1);

        ($assertProxy = Assert::ifEnabled())
        && $assertProxy->that(count($stackFrames) >= $callerStackFrameIndex + 1)
        && $assertProxy->info('count($stackFrames) >= $callerStackFrameIndex + 1', []);

        $stackFrame = $stackFrames[$callerStackFrameIndex];
        return new CallerInfo(
            $stackFrame['file'],
            (int)$stackFrame['line'],
            $stackFrame['class'] ?? null,
            $stackFrame['function']
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
    private static function formatValue($value): string
    {
        if (is_bool($value)) {
            return self::formatBool($value);
        }

        if (is_array($value)) {
            return self::formatArray($value);
        }

        if (is_object($value)) {
            return self::formatObject($value);
        }

        return '<' . self::getType($value) . '> ' . strval($value);
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
    private static function formatArray(array $arrayValue): string
    {
        $result = 'array [' . count($arrayValue) . ']{';
        $isFirst = true;
        foreach ($arrayValue as $key => $value) {
            if ($isFirst) {
                $result .= ', ';
                $isFirst = false;
            }
            $result .= self::formatValue($value);
        }
        $result .= '}';
        return $result;
    }

    private static function formatObject(object $objectValue): string
    {
        if (method_exists($objectValue, '__debugInfo')) {
            return self::formatValue($objectValue->__debugInfo());
        }

        if (method_exists($objectValue, '__toString')) {
            return $objectValue->__toString();
        }

        return self::getType($objectValue);
    }

    /**
     * @param array<mixed> $stackFrame
     *
     * @return string
     */
    private static function formatCurrentStackFrame(array $stackFrame): string
    {
        $result = '';
        if (array_key_exists('class', $stackFrame)) {
            $result .= $stackFrame['class'];
            $result .= '::';
        }
        $result .= $stackFrame['function'];
        $result .= ' called at [';
        $result .= basename($stackFrame['file']);
        $result .= ':';
        $result .= $stackFrame['line'];
        $result .= ']';
        if (array_key_exists('object', $stackFrame)) {
            $result .= ' | this: ';
            $result .= self::formatValue($stackFrame['object']);
        }
        $isFirstArg = true;
        foreach ($stackFrame['args'] as $arg) {
            if ($isFirstArg) {
                $isFirstArg = false;
                $result .= ' | args[';
                $result .= count($stackFrame['args']);
                $result .= ']: ';
            } else {
                $result .= ', ';
            }
            $result .= self::formatValue($arg);
        }
        return $result;
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
                $result .= self::formatCurrentStackFrame($stackFrame);
            }
            ++$index;
        }
        return $result;
    }
}
