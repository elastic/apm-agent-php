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

namespace Elastic\Apm\AutoInstrument;

use Throwable;

interface InterceptedCallTrackerInterface
{
    /**
     * @param object|null $interceptedCallThis
     * @param mixed[]     $interceptedCallArgs Intercepted call arguments
     *
     * @return void
     */
    public function preHook(?object $interceptedCallThis, array $interceptedCallArgs): void;

    /**
     * @param int             $numberOfStackFramesToSkip
     * @param bool            $hasExitedByException
     * @param mixed|Throwable $returnValueOrThrown Return value of the intercepted call or thrown object
     *
     * @noinspection PhpMissingParamTypeInspection
     */
    public function postHook(int $numberOfStackFramesToSkip, bool $hasExitedByException, $returnValueOrThrown): void;
}
