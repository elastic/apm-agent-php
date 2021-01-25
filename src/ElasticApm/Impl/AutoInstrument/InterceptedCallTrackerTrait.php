<?php

/** @noinspection PhpComposerExtensionStubsInspection */

declare(strict_types=1);

namespace Elastic\Apm\Impl\AutoInstrument;

use Elastic\Apm\Impl\Util\Assert;
use Elastic\Apm\SpanInterface;
use Throwable;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
trait InterceptedCallTrackerTrait
{
    /**
     * @param int             $numberOfStackFramesToSkip
     * @param SpanInterface   $span
     * @param bool            $hasExitedByException
     * @param mixed|Throwable $returnValueOrThrown Return value of the intercepted call or thrown object
     * @param float|null      $duration
     *
     * @noinspection PhpMissingParamTypeInspection
     */
    protected static function endSpan(
        int $numberOfStackFramesToSkip,
        SpanInterface $span,
        bool $hasExitedByException,
        $returnValueOrThrown,
        ?float $duration = null
    ): void {
        if ($hasExitedByException && is_object($returnValueOrThrown) && ($returnValueOrThrown instanceof Throwable)) {
            $span->createErrorFromThrowable($returnValueOrThrown);
        }
        // endSpanEx() is a public API so by default it will appear on the stacktrace
        // because it assumes that it was called by the application and
        // it is important to know from where in the application.
        // But in this case endSpanEx() is called by agent's code so there's no point for it
        // to appear on the stack trace.
        // That is the reason to +1 to the usual $numberOfStackFramesToSkip + 1
        $span->endSpanEx($numberOfStackFramesToSkip + 2, $duration);
    }

    /**
     * @param object|null $interceptedCallThis
     * @param mixed[]     $interceptedCallArgs Intercepted call arguments
     *
     * @return void
     */
    protected static function assertInterceptedCallThisIsNotNull(
        ?object $interceptedCallThis,
        array $interceptedCallArgs
    ): void {
        ($assertProxy = Assert::ifEnabled())
        && $assertProxy->that(!is_null($interceptedCallThis))
        && $assertProxy->withContext('!is_null($interceptedCallThis)', ['interceptedCallArgs' => $interceptedCallArgs]);
    }

    /**
     * @param object|null $interceptedCallThis
     * @param mixed[]     $interceptedCallArgs Intercepted call arguments
     *
     * @return void
     */
    protected static function assertInterceptedCallThisIsNull(
        ?object $interceptedCallThis,
        array $interceptedCallArgs
    ): void {
        ($assertProxy = Assert::ifEnabled())
        && $assertProxy->that(is_null($interceptedCallThis))
        && $assertProxy->withContext(
            'is_null($interceptedCallThis)',
            [
                'interceptedCallThis' => $interceptedCallThis,
                'interceptedCallArgs' => $interceptedCallArgs,
            ]
        );
    }

    /**
     * @param bool                 $hasExitedByException
     * @param array<string, mixed> $dbgCtx
     *
     * @return void
     */
    protected static function assertInterceptedCallNotExitedByException(
        bool $hasExitedByException,
        array $dbgCtx = []
    ): void {
        ($assertProxy = Assert::ifEnabled())
        && $assertProxy->that(!$hasExitedByException)
        && $assertProxy->withContext('!$hasExitedByException', $dbgCtx);
    }
}
