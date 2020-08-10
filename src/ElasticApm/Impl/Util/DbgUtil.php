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

    public const NULL_AS_STRING = /** @lang text */ '<null>';

    public static function getCallerInfoFromStacktrace(int $numberOfStackFramesToSkip): CallerInfo
    {
        $callerStackFrameIndex = $numberOfStackFramesToSkip + 1;
        $stackFrames = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, /* limit: */ $callerStackFrameIndex + 1);

        ($assertProxy = Assert::ifEnabled())
        && $assertProxy->that(count($stackFrames) >= $callerStackFrameIndex + 1)
        && $assertProxy->info(
            'count($stackFrames) >= $callerStackFrameIndex + 1',
            ['count($stackFrames)' => count($stackFrames), '$callerStackFrameIndex' => $callerStackFrameIndex]
        );

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

    // /**
    //  * @param mixed $value
    //  *
    //  * @return string
    //  */
    // public static function formatValue_CALL_ONLY_FROM_Log_infra($value): string
    // {
    //     try {
    //         return self::formatValueImpl($value);
    //     } catch (Throwable $thrown) {
    //         return '<' . 'FAILED to format value of type ' . self::getType($value)
    //                . ': ' . self::formatThrowable($thrown) . '>';
    //     }
    // }

    public static function formatSourceCodeFilePath(string $srcFile): string
    {
        // TODO: Sergey Kleyman: Implement: cutoff only shared prefix
        return basename($srcFile);
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
