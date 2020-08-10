<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\Util;

use Elastic\Apm\Impl\Util\DbgUtil;
use Elastic\Apm\Impl\Util\ExceptionUtil;
use RuntimeException;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
abstract class TestDtoBase
{
    public static function buildUnsupportedMethodException(string $calledMethodName): RuntimeException
    {
        $msgStart = "Method `$calledMethodName' is not supported by " . DbgUtil::fqToShortClassName(get_class());

        return new RuntimeException(
            ExceptionUtil::buildMessageWithStacktrace($msgStart, /* numberOfStackFramesToSkip */ 1)
        );
    }
}
