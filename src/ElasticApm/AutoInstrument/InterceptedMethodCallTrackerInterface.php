<?php

/** @noinspection PhpUndefinedClassInspection */

declare(strict_types=1);

namespace Elastic\Apm\AutoInstrument;

interface InterceptedMethodCallTrackerInterface
{
    /**
     * @param mixed $thisObj
     * @param mixed ...$interceptedCallArgs Intercepted call arguments
     *
     * @return void
     */
    public function preHook($thisObj, ...$interceptedCallArgs): void;

    /**
     * @param bool            $hasExitedByException
     * @param mixed|Throwable $returnValueOrThrown Return value of the intercepted call or thrown object
     */
    public function postHook(bool $hasExitedByException, $returnValueOrThrown): void;
}
