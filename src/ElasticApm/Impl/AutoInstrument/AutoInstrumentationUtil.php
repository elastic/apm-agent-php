<?php

/** @noinspection PhpComposerExtensionStubsInspection */

declare(strict_types=1);

namespace Elastic\Apm\Impl\AutoInstrument;

use Elastic\Apm\Impl\Util\DbgUtil;
use Elastic\Apm\Impl\Util\StaticClassTrait;
use Elastic\Apm\SpanInterface;
use Throwable;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class AutoInstrumentationUtil
{
    use StaticClassTrait;

    /**
     * @param SpanInterface   $span
     * @param bool            $hasExitedByException
     * @param mixed|Throwable $returnValueOrThrown Return value of the intercepted call or thrown object
     */
    public static function endSpan(SpanInterface $span, bool $hasExitedByException, $returnValueOrThrown): void
    {
        $span->setLabel('returnValueOrThrown type', DbgUtil::getType($returnValueOrThrown));
        $span->setLabel('hasExitedByException', $hasExitedByException);
        $span->end();
    }
}
