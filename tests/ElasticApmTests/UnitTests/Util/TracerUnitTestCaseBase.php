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

use Closure;
use Elastic\Apm\Impl\GlobalTracerHolder;
use Elastic\Apm\Impl\TracerInterface;
use ElasticApmTests\Util\TestCaseBase;
use ElasticApmTests\Util\TracerBuilderForTests;

class TracerUnitTestCaseBase extends TestCaseBase
{
    /** @var MockEventSink */
    protected $mockEventSink;

    /** @var TracerInterface */
    protected $tracer;

    /** @inheritDoc */
    public function setUp(): void
    {
        parent::setUp();

        $this->setUpTestEnv();
    }

    /**
     * @param null|Closure(TracerBuilderForTests): void $tracerBuildCallback
     * @param bool                                      $shouldCreateMockEventSink
     *
     * @return void
     */
    protected function setUpTestEnv(?Closure $tracerBuildCallback = null, bool $shouldCreateMockEventSink = true): void
    {
        if ($shouldCreateMockEventSink) {
            $this->mockEventSink = new MockEventSink();
        }

        $builder = self::buildTracerForTests($shouldCreateMockEventSink ? $this->mockEventSink : null);

        if ($tracerBuildCallback !== null) {
            $tracerBuildCallback($builder);
        }

        $this->tracer = $builder->build();
        GlobalTracerHolder::setValue($this->tracer);
    }

    /** @inheritDoc */
    public function tearDown(): void
    {
        GlobalTracerHolder::unsetValue();

        parent::tearDown();
    }
}
