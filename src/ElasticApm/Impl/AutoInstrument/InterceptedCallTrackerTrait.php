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
        /** @noinspection PhpUnusedParameterInspection */
        bool $hasExitedByException,
        /** @noinspection PhpUnusedParameterInspection */
        $returnValueOrThrown,
        ?float $duration = null
    ): void {
        $span->endSpanEx($numberOfStackFramesToSkip + 1, $duration);
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
