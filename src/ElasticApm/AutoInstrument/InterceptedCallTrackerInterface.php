<?php

declare(strict_types=1);

namespace Elastic\Apm\AutoInstrument;

use Throwable;

interface InterceptedCallTrackerInterface
{
    /**
     * @param object|null $interceptedCallThis
     * @param mixed[]     $interceptedCallArgs Intercepted call arguments
     *
     * @return void
     */
    public function preHook(?object $interceptedCallThis, array $interceptedCallArgs): void;

    /**
     * @param int             $numberOfStackFramesToSkip
     * @param bool            $hasExitedByException
     * @param mixed|Throwable $returnValueOrThrown Return value of the intercepted call or thrown object
     *
     * @noinspection PhpMissingParamTypeInspection
     */
    public function postHook(int $numberOfStackFramesToSkip, bool $hasExitedByException, $returnValueOrThrown): void;
}
