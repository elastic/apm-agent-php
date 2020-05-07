<?php

/** @noinspection PhpUndefinedClassInspection */

declare(strict_types=1);

namespace Elastic\Apm\AutoInstrument;

interface OnInterceptedCallEndInterface
{
    /**
     * @param bool            $hasExitedByException
     * @param mixed|Throwable $returnValueOrThrown Return value of the intercepted call or thrown object
     */
    public function onInterceptedCallEnd(bool $hasExitedByException, $returnValueOrThrown): void;
}
