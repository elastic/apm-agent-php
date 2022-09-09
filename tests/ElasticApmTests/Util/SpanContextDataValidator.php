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

use Elastic\Apm\Impl\SpanContextData;
use PHPUnit\Framework\TestCase;

final class SpanContextDataValidator extends DataValidator
{
    /** @var SpanContextDataExpectations */
    protected $expectations;

    /** @var ?SpanContextData */
    protected $actual;

    protected function __construct(
        SpanContextDataExpectations $expectations,
        ?SpanContextData $actual
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

        SpanDataValidator::validateExecutionSegmentContextData($this->actual);

        SpanContextDbDataValidator::validate($this->actual->db, $this->expectations->db);
        SpanContextDestinationDataValidator::validate($this->actual->destination, $this->expectations->destination);
        SpanContextHttpDataValidator::validate($this->actual->http, $this->expectations->http);
        SpanContextServiceDataValidator::validate($this->actual->service, $this->expectations->service);
    }

    public static function validate(
        ?SpanContextData $actual,
        ?SpanContextDataExpectations $expectations = null
    ): void {
        (new self($expectations ?? new SpanContextDataExpectations(), $actual))->validateImpl();
    }
}
