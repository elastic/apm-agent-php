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

namespace ElasticApmTests\UnitTests\Util;

use ElasticApmTests\Util\ExecutionSegmentDto;
use ElasticApmTests\Util\TransactionDto;

final class MockTransaction extends TransactionDto
{
    use MockExecutionSegmentTrait;

    /** @var MockTracer */
    public $tracer;

    public function __construct(?string $name, MockTracer $tracer, ?ExecutionSegmentDto $parent)
    {
        $this->tracer = $tracer;
        $this->constructMockExecutionSegmentDataTrait($name);
        if ($parent === null) {
            $this->traceId = $this->tracer->generateTraceId();
        } else {
            $this->traceId = $parent->traceId;
            $this->parentId = $parent->id;
        }
    }

    protected function getTransaction(): MockTransaction
    {
        return $this;
    }
}
