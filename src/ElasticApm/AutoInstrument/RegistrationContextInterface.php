<?php

/** @noinspection PhpUndefinedClassInspection */

declare(strict_types=1);

namespace Elastic\Apm\AutoInstrument;

interface RegistrationContextInterface
{
    /**
     * @param string   $className
     * @param string   $methodName
     * @param callable $interceptedMethodCallTrackerFactory
     *
     * @phpstan-param callable(): InterceptedMethodCallTrackerInterface $interceptedMethodCallTrackerFactory
     */
    public function interceptCallsToMethod(
        string $className,
        string $methodName,
        callable $interceptedMethodCallTrackerFactory
    ): void;

    /**
     * @param string   $functionName
     * @param callable $interceptedFunctionCallTrackerFactory
     *
     * @phpstan-param callable(): InterceptedFunctionCallTrackerInterface $interceptedFunctionCallTrackerFactory
     */
    public function interceptCallsToFunction(
        string $functionName,
        callable $interceptedFunctionCallTrackerFactory
    ): void;
}
