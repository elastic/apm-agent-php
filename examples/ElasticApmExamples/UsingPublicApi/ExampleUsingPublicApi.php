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

/** @noinspection PhpInternalEntityUsedInspection */

declare(strict_types=1);

namespace ElasticApmExamples\UsingPublicApi;

use Elastic\Apm\Impl\TracerBuilder;
use ElasticApmTests\UnitTests\Util\MockEventSink;
use ElasticApmTests\UnitTests\Util\TracerUnitTestCaseBase;

class ExampleUsingPublicApi extends TracerUnitTestCaseBase
{
    public function main(): void
    {
        // Arrange
        $mockEventSink = new MockEventSink();
        $tracer = TracerBuilder::startNew()->withEventSink($mockEventSink)->build();

        // Act
        $tx = $tracer->beginTransaction('test_TX_name', 'test_TX_type');
        $tx->end();

        // Assert
        $this->assertEmpty($mockEventSink->idToSpan());
        $reportedTx = $mockEventSink->singleTransaction();
        $this->assertSame('test_TX_name', $reportedTx->name);
        $this->assertSame('test_TX_type', $reportedTx->type);

        echo 'Completed successfully' . PHP_EOL;
    }
}
