<?php

/** @noinspection PhpUndefinedClassInspection */

declare(strict_types=1);

namespace Elastic\Apm\AutoInstrument;

interface RegistrationContextInterface
{
    /**
     * @param string   $className
     * @param string   $methodName
     * @param callable $onInterceptedCallBegin
     */
    public function interceptCallsToMethod(
        string $className,
        string $methodName,
        callable $onInterceptedCallBegin
    ): void;

    /**
     * @param string   $functionName
     * @param callable $onInterceptedCallBegin
     */
    public function interceptCallsToFunction(
        string $functionName,
        callable $onInterceptedCallBegin
    ): void;
}
