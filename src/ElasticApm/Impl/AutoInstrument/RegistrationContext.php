<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\AutoInstrument;

use Elastic\Apm\AutoInstrument\RegistrationContextInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class RegistrationContext implements RegistrationContextInterface
{
    /** @var Registration[] */
    public $interceptedCallRegistrations;

    /** @var int */
    public $dbgCurrentPluginIndex;

    /** @var string */
    public $dbgCurrentPluginDesc;

    public function interceptCallsToMethod(
        string $className,
        string $methodName,
        callable $interceptedCallTrackerFactory
    ): void {
        /**
         * elastic_apm_* functions are provided by the elastic_apm extension
         *
         * @noinspection PhpFullyQualifiedNameUsageInspection, PhpUndefinedFunctionInspection
         * @phpstan-ignore-next-line
         */
        $interceptRegistrationId = \elastic_apm_intercept_calls_to_internal_method($className, $methodName);
        if ($interceptRegistrationId >= 0) {
            $this->interceptedCallRegistrations[$interceptRegistrationId] = new Registration(
                $this->dbgCurrentPluginIndex,
                $this->dbgCurrentPluginDesc,
                $className . '::' . $methodName /* <- dbgInterceptedCallDesc */,
                $interceptedCallTrackerFactory
            );
        }
    }

    public function interceptCallsToFunction(
        string $functionName,
        callable $interceptedCallTrackerFactory
    ): void {
        /**
         * elastic_apm_* functions are provided by the elastic_apm extension
         *
         * @noinspection PhpFullyQualifiedNameUsageInspection, PhpUndefinedFunctionInspection
         * @phpstan-ignore-next-line
         */
        $interceptRegistrationId = \elastic_apm_intercept_calls_to_internal_function($functionName);
        if ($interceptRegistrationId >= 0) {
            $this->interceptedCallRegistrations[$interceptRegistrationId] = new Registration(
                $this->dbgCurrentPluginIndex,
                $this->dbgCurrentPluginDesc,
                $functionName /* <- dbgInterceptedCallDesc */,
                $interceptedCallTrackerFactory
            );
        }
    }
}
