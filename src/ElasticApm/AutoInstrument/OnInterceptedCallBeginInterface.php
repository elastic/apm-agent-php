<?php

/** @noinspection PhpUndefinedClassInspection */

declare(strict_types=1);

namespace Elastic\Apm\AutoInstrument;

interface OnInterceptedCallBeginInterface
{
    /**
     * @param mixed ...$interceptedCallArgs Intercepted call arguments
     *
     * @return OnInterceptedCallEndInterface|null
     */
    public function onInterceptedCallBegin(...$interceptedCallArgs): ?OnInterceptedCallEndInterface;
}
