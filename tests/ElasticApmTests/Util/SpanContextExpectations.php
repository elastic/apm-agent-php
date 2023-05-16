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

class SpanContextExpectations extends ExecutionSegmentContextExpectations
{
    /** @var Optional<?SpanContextDbExpectations> */
    public $db;

    /** @var Optional<?SpanContextDestinationExpectations> */
    public $destination;

    /** @var Optional<?SpanContextHttpExpectations> */
    public $http;

    /** @var Optional<?SpanContextServiceExpectations> */
    public $service;

    public function __construct()
    {
        parent::__construct();
        $this->db = new Optional();
        $this->destination = new Optional();
        $this->http = new Optional();
        $this->service = new Optional();
    }

    public function ensureNotNullDb(): SpanContextDbExpectations
    {
        if ($this->db->isValueSet()) {
            $value = $this->db->getValue();
            TestCaseBase::assertNotNull($value);
            return $value;
        }

        $value = new SpanContextDbExpectations();
        $this->db->setValue($value);
        return $value;
    }

    public function ensureNotNullDestination(): SpanContextDestinationExpectations
    {
        if ($this->destination->isValueSet()) {
            $value = $this->destination->getValue();
            TestCaseBase::assertNotNull($value);
            return $value;
        }

        $value = new SpanContextDestinationExpectations();
        $this->destination->setValue($value);
        return $value;
    }

    public function ensureNotNullService(): SpanContextServiceExpectations
    {
        if ($this->service->isValueSet()) {
            $value = $this->service->getValue();
            TestCaseBase::assertNotNull($value);
            return $value;
        }

        $value = new SpanContextServiceExpectations();
        $this->service->setValue($value);
        return $value;
    }

    public function assumeNotNullService(): SpanContextServiceExpectations
    {
        TestCaseBase::assertNotNull($this->service->isValueSet());
        $value = $this->service->getValue();
        TestCaseBase::assertNotNull($value);
        return $value;
    }
}
