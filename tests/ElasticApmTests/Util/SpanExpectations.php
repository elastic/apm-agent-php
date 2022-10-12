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

use Elastic\Apm\Impl\StackTraceFrame;

final class SpanExpectations extends ExecutionSegmentExpectations
{
    /** @var Optional<?string> */
    public $action = null;

    /** @var SpanContextExpectations */
    public $context;

    /** @var Optional<?string> */
    public $subtype = null;

    /** @var null|StackTraceFrame[] */
    public $stackTrace = null;

    /** @var ?bool */
    public $allowExpectedStackTraceToBePrefix = null;

    public function __construct()
    {
        parent::__construct();
        $this->action = new Optional();
        $this->context = new SpanContextExpectations();
        $this->subtype = new Optional();
    }
}
