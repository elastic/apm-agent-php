<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Util;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class ExceptionUtil
{
    use StaticClassTrait;

    public static function buildMessageWithStacktrace(string $msgStart): string
    {
        $message = $msgStart;
        $message .= '. Stack trace:';
        $message .= PHP_EOL;
        $message .= TextUtil::indent(DbgUtil::formatCurrentStackTrace(/* numberOfStackFramesToSkip: */ 1));

        return $message;
    }
}
