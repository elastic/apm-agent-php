<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Util;

use Elastic\Apm\Impl\Log\AdhocLoggableObject;
use Elastic\Apm\Impl\Log\LoggablePhpStacktrace;
use Elastic\Apm\Impl\Log\LoggableToString;
use Elastic\Apm\Impl\Log\PropertyLogPriority;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class ExceptionUtil
{
    use StaticClassTrait;

    /**
     * @param string               $messagePrefix
     * @param array<string, mixed> $context
     * @param int                  $numberOfStackTraceFramesToSkip PHP_INT_MAX means no stack trace
     *
     * @return string
     */
    public static function buildMessage(
        string $messagePrefix,
        array $context,
        int $numberOfStackTraceFramesToSkip = PHP_INT_MAX
    ): string {
        $messageSuffixObj = new AdhocLoggableObject($context);
        if ($numberOfStackTraceFramesToSkip !== PHP_INT_MAX) {
            $stacktrace = LoggablePhpStacktrace::buildForCurrent($numberOfStackTraceFramesToSkip + 1);
            $messageSuffixObj->addProperties(
                [LoggablePhpStacktrace::STACK_TRACE_KEY => $stacktrace],
                PropertyLogPriority::MUST_BE_INCLUDED
            );
        }
        $messageSuffix = LoggableToString::convert($messageSuffixObj, /* prettyPrint */ true);
        return $messagePrefix . (TextUtil::isEmptyString($messageSuffix) ? '' : ('. ' . $messageSuffix));
    }
}
