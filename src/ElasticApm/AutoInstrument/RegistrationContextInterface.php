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
    public function interceptCallsToInternalMethod(
        string $className,
        string $methodName,
        OnInterceptedCallBeginInterface $onInterceptedCallBegin
    ): void;

    /**
     * @param string                          $functionName
     * @param OnInterceptedCallBeginInterface $onInterceptedCallBegin
     */
    public function interceptCallsToInternalFunction(
        string $functionName,
        OnInterceptedCallBeginInterface $onInterceptedCallBegin
    ): void;
}
