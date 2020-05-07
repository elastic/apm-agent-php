<?php

/** @noinspection PhpUndefinedClassInspection */

declare(strict_types=1);

namespace Elastic\Apm\AutoInstrument;

interface RegistrationContextInterface
{
    /**
     * @param string                          $className
     * @param string                          $methodName
     * @param OnInterceptedCallBeginInterface $onInterceptedCallBegin
     */
    public function interceptCallsToMethod(
        string $className,
        string $methodName,
        OnInterceptedCallBeginInterface $onInterceptedCallBegin
    ): void;

    /**
     * @param string                          $functionName
     * @param OnInterceptedCallBeginInterface $onInterceptedCallBegin
     */
    public function interceptCallsToFunction(
        string $functionName,
        OnInterceptedCallBeginInterface $onInterceptedCallBegin
    ): void;
}
