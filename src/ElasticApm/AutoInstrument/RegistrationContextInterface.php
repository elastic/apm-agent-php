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
     * @param string   $className
     * @param string   $methodName
     * @param callable $onInterceptedCallBegin
     */
    public function interceptCallsToMethod2(
        string $className,
        string $methodName,
        CallbackFactoryInterface $callback
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
