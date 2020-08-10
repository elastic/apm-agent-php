<?php

/** @noinspection PhpUnusedAliasInspection */

declare(strict_types=1);

namespace Elastic\Apm\AutoInstrument;

interface RegistrationContextInterface
{
    /**
     * @param string   $className
     * @param string   $methodName
     * @param callable $interceptedCallTrackerFactory
     *
     * @phpstan-param callable(): InterceptedCallTrackerInterface $interceptedCallTrackerFactory
     */
    public function interceptCallsToMethod(
        string $className,
        string $methodName,
        callable $interceptedCallTrackerFactory
    ): void;

    /**
     * @param string   $functionName
     * @param callable $interceptedCallTrackerFactory
     *
     * @phpstan-param callable(): InterceptedCallTrackerInterface $interceptedCallTrackerFactory
     */
    public function interceptCallsToFunction(
        string $functionName,
        callable $interceptedCallTrackerFactory
    ): void;
}
