<?php

/*
 * Licensed to Elasticsearch B.V. under one or more contributor
 * license agreements. See the NOTICE file distributed with
 * this work for additional information regarding copyright
 * ownership. Elasticsearch B.V. licenses this file to you under
 * the Apache License, Version 2.0 (the "License"); you may
 * not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations
 * under the License.
 */

declare(strict_types=1);

namespace Elastic\Apm\Impl\AutoInstrument;

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
        callable $preHook
    ): void {
        /**
         * elastic_apm_* functions are provided by the elastic_apm extension
         *
         * @noinspection PhpFullyQualifiedNameUsageInspection, PhpUndefinedFunctionInspection
         * @phpstan-ignore-next-line
         */
        $interceptRegistrationId = \elastic_apm_intercept_calls_to_internal_method(
            // PHP internals store classes, methods and functions in a hashtable
            // where key is a name converted to lower case
            strtolower($className),
            strtolower($methodName)
        );
        if ($interceptRegistrationId >= 0) {
            $this->interceptedCallRegistrations[$interceptRegistrationId] = new Registration(
                $this->dbgCurrentPluginIndex,
                $this->dbgCurrentPluginDesc,
                $className . '::' . $methodName /* <- dbgInterceptedCallDesc */,
                $preHook
            );
        }
    }

    public function interceptCallsToFunction(
        string $functionName,
        callable $preHook
    ): void {
        /**
         * elastic_apm_* functions are provided by the elastic_apm extension
         *
         * @noinspection PhpFullyQualifiedNameUsageInspection, PhpUndefinedFunctionInspection
         * @phpstan-ignore-next-line
         */
        // PHP internals store classes, methods and functions in a hashtable
        // where key is a name converted to lower case
        $interceptRegistrationId = \elastic_apm_intercept_calls_to_internal_function(strtolower($functionName));
        if ($interceptRegistrationId >= 0) {
            $this->interceptedCallRegistrations[$interceptRegistrationId] = new Registration(
                $this->dbgCurrentPluginIndex,
                $this->dbgCurrentPluginDesc,
                $functionName /* <- dbgInterceptedCallDesc */,
                function (
                    ?object $interceptedCallThis,
                    array $interceptedCallArgs
                ) use ($preHook): ?callable {
                    return $preHook($interceptedCallArgs);
                }
            );
        }
    }
}
