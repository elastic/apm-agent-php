<?php

/** @noinspection PhpUndefinedClassInspection */

declare(strict_types=1);

namespace Elastic\Apm\AutoInstrument;

interface InterceptedCallTrackerInterface
{
    /**
     * @param object|null $interceptedCallThis
     * @param mixed       ...$interceptedCallArgs Intercepted call arguments
     *
     * @return void
     */
    public function preHook(?object $interceptedCallThis, ...$interceptedCallArgs): void;

    /**
     * @param bool            $hasExitedByException
     * @param mixed|Throwable $returnValueOrThrown Return value of the intercepted call or thrown object
     */
    public function postHook(bool $hasExitedByException, $returnValueOrThrown): void;
}
