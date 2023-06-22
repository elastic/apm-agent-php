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

namespace ElasticApmTests\Util;

use PHPUnit\Framework\Assert;

final class AssertMessageStackScopeAutoRef
{
    /** @var AssertMessageStack */
    private $stack;

    /** @var ?AssertMessageStackScopeData */
    private $data;

    public function __construct(AssertMessageStack $stack, ?AssertMessageStackScopeData $data)
    {
        $this->stack = $stack;
        $this->data = $data;
    }

    public function __destruct()
    {
        if ($this->data === null) {
            return;
        }

        $this->stack->autoPopScope($this->data);
    }

    /**
     * @param array<string, mixed> $ctx
     */
    public function add(array $ctx): void
    {
        if ($this->data === null) {
            return;
        }

        ArrayUtilForTests::append(/* from */ $ctx, /* to */ $this->data->subScopesStack[count($this->data->subScopesStack) - 1]->second);
    }

    /**
     * @param array<string, mixed> $initialCtx
     */
    public function pushSubScope(array $initialCtx = []): void
    {
        if ($this->data === null) {
            return;
        }

        Assert::assertGreaterThanOrEqual(1, count($this->data->subScopesStack));
        $this->data->subScopesStack[] = new Pair(AssertMessageStackScopeData::buildContextName(/* numberOfStackFramesToSkip */ 1), $initialCtx);
        Assert::assertGreaterThanOrEqual(2, count($this->data->subScopesStack));
    }

    /**
     * @param array<string, mixed> $initialCtx
     */
    public function clearCurrentSubScope(array $initialCtx = []): void
    {
        if ($this->data === null) {
            return;
        }

        Assert::assertGreaterThanOrEqual(2, count($this->data->subScopesStack));
        $this->data->subScopesStack[count($this->data->subScopesStack) - 1]->second = $initialCtx;
    }

    public function popSubScope(): void
    {
        if ($this->data === null) {
            return;
        }

        Assert::assertGreaterThanOrEqual(2, count($this->data->subScopesStack));
        array_pop(/* ref */ $this->data->subScopesStack);
        Assert::assertGreaterThanOrEqual(1, count($this->data->subScopesStack));
    }
}
