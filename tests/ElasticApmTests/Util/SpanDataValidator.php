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
use Elastic\Apm\Impl\SpanContextDbData;
use Elastic\Apm\Impl\SpanContextDestinationData;
use Elastic\Apm\Impl\SpanContextDestinationServiceData;
use Elastic\Apm\Impl\SpanContextHttpData;
use Elastic\Apm\Impl\SpanData;

final class SpanDataValidator extends ExecutionSegmentDataValidator
{
    /** @var SpanDataExpected */
    protected $expected;

    /** @var SpanData */
    protected $actual;

    protected function __construct(SpanDataExpected $expected, SpanData $actual)
    {
        parent::__construct($expected, $actual);

        $this->expected = $expected;
        $this->actual = $actual;
    }

    protected function validateImpl(): void
    {
        parent::validateImpl();

        self::validateNullableKeywordString($this->actual->action);
        self::validateId($this->actual->parentId);
        if ($this->actual->stacktrace !== null) {
            self::validateStacktrace($this->actual->stacktrace);
        }
        self::validateNullableKeywordString($this->actual->subtype);

        if ($this->actual->context !== null) {
            self::validateContextData($this->actual->context);
        }
    }

    public static function validate(SpanData $actual, ?SpanDataExpected $expected = null): void
    {
        (new self($expected ?? new SpanDataExpected(), $actual))->validateImpl();
    }

    public static function validateContextDbData(SpanContextDbData $obj): void
    {
        self::validateNullableNonKeywordString($obj->statement);
    }

    public static function validateContextHttpData(SpanContextHttpData $obj): void
    {
        self::validateNullableNonKeywordString($obj->url);
        self::validateNullableHttpStatusCode($obj->statusCode);
        self::validateNullableKeywordString($obj->method);
    }

    public static function validateContextDestinationServiceData(SpanContextDestinationServiceData $obj): void
    {
        self::validateKeywordString($obj->name);
        self::validateKeywordString($obj->resource);
        self::validateKeywordString($obj->type);
    }

    public static function validateContextDestinationData(SpanContextDestinationData $obj): void
    {
        if ($obj->service !== null) {
            self::validateContextDestinationServiceData($obj->service);
        }
    }

    public static function validateContextData(SpanContextData $obj): void
    {
        self::validateExecutionSegmentContextData($obj);

        if ($obj->db !== null) {
            self::validateContextDbData($obj->db);
        }

        if ($obj->http !== null) {
            self::validateContextHttpData($obj->http);
        }

        if ($obj->destination !== null) {
            self::validateContextDestinationData($obj->destination);
        }
    }
}
