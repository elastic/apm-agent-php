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

use Elastic\Apm\Impl\SpanData;

final class SpanDataValidator extends ExecutionSegmentDataValidator
{
    /** @var SpanDataExpectations */
    protected $expectations;

    /** @var SpanData */
    protected $actual;

    protected function __construct(SpanDataExpectations $expectations, SpanData $actual)
    {
        parent::__construct($expectations, $actual);

        $this->expectations = $expectations;
        $this->actual = $actual;
    }

    protected function validateImpl(): void
    {
        parent::validateImpl();

        self::assertSameNullableKeywordStringExpectedOptional($this->expectations->action, $this->actual->action);

        if ($this->actual->context !== null) {
            SpanContextDataValidator::validate($this->actual->context, $this->expectations->context);
        }

        self::validateId($this->actual->parentId);

        if ($this->actual->stacktrace !== null) {
            self::validateStacktrace($this->actual->stacktrace);
        }

        self::assertSameNullableKeywordStringExpectedOptional($this->expectations->subtype, $this->actual->subtype);
    }

    public static function validate(SpanData $actual, ?SpanDataExpectations $expectations = null): void
    {
        (new self($expectations ?? new SpanDataExpectations(), $actual))->validateImpl();
    }
}
