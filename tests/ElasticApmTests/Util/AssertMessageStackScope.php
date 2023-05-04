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

final class AssertMessageStackScope
{
    /** @var AssertMessageStack */
    private $stack;

    /** @var AssertMessageStackScopeData */
    private $data;

    public function __construct(AssertMessageStack $stack, AssertMessageStackScopeData $data)
    {
        TestCaseBase::assertSame(1, $data->refsFromStackCount);
        $this->stack = $stack;
        $this->data = $data;
    }

    public function __destruct()
    {
        if ($this->data->refsFromStackCount !== 0) {
            $this->stack->removeScope($this->data);
        }
    }

    /**
     * @param array<string, mixed> $ctx
     */
    public function add(array $ctx): void
    {
        ArrayUtilForTests::append(/* from */ $ctx, /* to */ $this->data->ctx);
    }
}
