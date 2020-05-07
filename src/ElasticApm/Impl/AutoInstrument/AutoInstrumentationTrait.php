<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\AutoInstrument;

use Elastic\Apm\AutoInstrument\RegistrationContextInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
trait AutoInstrumentationTrait
{
    /**
     * @param RegistrationContextInterface $ctx
     * @param string                       $className
     * @param string                       $methodName
     * @param callable                     $interceptedCallToSpanBaseFactory
     *
     * @phpstan-param callable(): InterceptedCallToSpanBase $interceptedCallToSpanBaseFactory
     */
    protected static function interceptCallsToMethod(
        RegistrationContextInterface $ctx,
        string $className,
        string $methodName,
        callable $interceptedCallToSpanBaseFactory
    ): void {
        $ctx->interceptCallsToMethod(
            $className,
            $methodName,
            InterceptedCallToSpanBase::wrap($interceptedCallToSpanBaseFactory)
        );
    }

    /**
     * @param RegistrationContextInterface $ctx
     * @param string                       $functionName
     * @param callable                     $interceptedCallToSpanBaseFactory
     *
     * @phpstan-param callable(): InterceptedCallToSpanBase $interceptedCallToSpanBaseFactory
     */
    protected static function interceptCallsToFunction(
        RegistrationContextInterface $ctx,
        string $functionName,
        callable $interceptedCallToSpanBaseFactory
    ): void {
        $ctx->interceptCallsToFunction(
            $functionName,
            InterceptedCallToSpanBase::wrap($interceptedCallToSpanBaseFactory)
        );
    }
}
