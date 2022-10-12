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

namespace ElasticApmTests\UnitTests;

use Elastic\Apm\ElasticApm;
use ElasticApmTests\TestsSharedCode\StackTraceTestSharedCode;
use ElasticApmTests\UnitTests\Util\TracerUnitTestCaseBase;

class StackTraceUnitTest extends TracerUnitTestCaseBase
{
    public function testAllSpanCreatingApis(): void
    {
        // Act

        $tx = ElasticApm::beginCurrentTransaction(__FUNCTION__, 'test_TX_type');

        /** @var array<string, mixed> $expectedData */
        $expectedData = [];

        $createSpanApis = StackTraceTestSharedCode::allSpanCreatingApis(/* ref */ $expectedData);
        foreach ($createSpanApis as $createSpan) {
            (new StackTraceTestSharedCode())->actPartImpl($createSpan, /* ref */ $expectedData);
        }

        $tx->end();

        // Assert

        $this->assertSame(__FUNCTION__, $this->mockEventSink->singleTransaction()->name);
        StackTraceTestSharedCode::assertPartImpl(
            count($createSpanApis),
            $expectedData,
            $this->mockEventSink->idToSpan()
        );
    }
}
