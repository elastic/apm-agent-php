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

use Elastic\Apm\Impl\SpanContextHttpData;
use PHPUnit\Framework\TestCase;

final class SpanContextHttpDataValidator extends DataValidator
{
    /** @var SpanContextHttpDataExpectations */
    protected $expectations;

    /** @var ?SpanContextHttpData */
    protected $actual;

    protected function __construct(
        SpanContextHttpDataExpectations $expectations,
        ?SpanContextHttpData $actual
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

        self::assertSameNullableNonKeywordStringExpectedOptional(
            $this->expectations->url,
            $this->actual->url
        );

        self::validateNullableHttpStatusCode($this->actual->statusCode);
        TestCaseBase::assertSameExpectedOptional($this->expectations->statusCode, $this->actual->statusCode);

        self::assertSameNullableKeywordStringExpectedOptional(
            $this->expectations->method,
            $this->actual->method
        );
    }

    public static function validate(
        ?SpanContextHttpData $actual,
        ?SpanContextHttpDataExpectations $expectations = null
    ): void {
        (new self($expectations ?? new SpanContextHttpDataExpectations(), $actual))->validateImpl();
    }
}
