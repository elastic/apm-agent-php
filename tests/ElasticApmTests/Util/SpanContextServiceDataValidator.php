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

use Elastic\Apm\Impl\SpanContextServiceData;
use PHPUnit\Framework\TestCase;

final class SpanContextServiceDataValidator extends DataValidator
{
    /** @var SpanContextServiceDataExpectations */
    protected $expectations;

    /** @var ?SpanContextServiceData */
    protected $actual;

    protected function __construct(
        SpanContextServiceDataExpectations $expectations,
        ?SpanContextServiceData $actual
    ) {
        $this->expectations = $expectations;
        $this->actual = $actual;
    }

    protected function validateImpl(): void
    {
        if ($this->actual === null) {
            TestCase::assertTrue($this->expectations->isEmpty());
            return;
        }

        SpanContextServiceTargetDataValidator::validate($this->actual->target, $this->expectations->target);
    }

    public static function validate(
        ?SpanContextServiceData $actual,
        ?SpanContextServiceDataExpectations $expectations = null
    ): void {
        (new self($expectations ?? new SpanContextServiceDataExpectations(), $actual))->validateImpl();
    }
}
