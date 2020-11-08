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
}
