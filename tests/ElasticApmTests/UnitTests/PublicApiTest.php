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

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace ElasticApmTests\UnitTests;

use Elastic\Apm\ElasticApm;
use ElasticApmTests\UnitTests\Util\NotFoundException;
use ElasticApmTests\UnitTests\Util\TracerUnitTestCaseBase;

class PublicApiTest extends TracerUnitTestCaseBase
{
    public function testBeginEndTransaction(): void
    {
        // Arrange
        $this->assertFalse($this->tracer->isNoop());

        // Act
        $tx = $this->tracer->beginTransaction('test_TX_name', 'test_TX_type');
        $this->assertFalse($tx->isNoop());
        $tx->end();

        // Assert
        $this->assertEmpty($this->mockEventSink->idToSpan());
        $reportedTx = $this->mockEventSink->singleTransaction();
        $this->assertSame('test_TX_name', $reportedTx->name);
        $this->assertSame('test_TX_type', $reportedTx->type);
    }

    public function testBeginEndSpan(): void
    {
        // Act
        $tx = $this->tracer->beginTransaction('test_TX_name', 'test_TX_type');
        $span_1 = $tx->beginChildSpan('test_span_1_name', 'test_span_1_type');
        // spans can overlap in any desired way
        $span_2 = $tx->beginChildSpan(
            'test_span_2_name',
            'test_span_2_type',
            'test_span_2_subtype',
            'test_span_2_action'
        );
        $span_2_1 = $span_2->beginChildSpan('test_span_2_1_name', 'test_span_2_1_type', 'test_span_2_1_subtype');
        $span_2_2 = $span_2->beginChildSpan(
            'test_span_2_2_name',
            'test_span_2_2_type',
            /* subtype: */ null,
            'test_span_2_2_action'
        );
        $span_1->end();
        $span_2_2->end();
        // parent span can end before its child spans
        $span_2->end();
        $span_2_1->end();
        $tx->end();

        // Assert
        $reportedTx = $this->mockEventSink->singleTransaction();
        $this->assertSame('test_TX_name', $reportedTx->name);
        $this->assertSame('test_TX_type', $reportedTx->type);

        $this->assertCount(4, $this->mockEventSink->idToSpan());

        $reportedSpan_1 = $this->mockEventSink->spanByName('test_span_1_name');
        $this->assertSame('test_span_1_type', $reportedSpan_1->type);
        $this->assertNull($reportedSpan_1->subtype);
        $this->assertNull($reportedSpan_1->action);

        $reportedSpan_2 = $this->mockEventSink->spanByName('test_span_2_name');
        $this->assertSame('test_span_2_type', $reportedSpan_2->type);
        $this->assertSame('test_span_2_subtype', $reportedSpan_2->subtype);
        $this->assertSame('test_span_2_action', $reportedSpan_2->action);

        $reportedSpan_2_1 = $this->mockEventSink->spanByName('test_span_2_1_name');
        $this->assertSame('test_span_2_1_type', $reportedSpan_2_1->type);
        $this->assertSame('test_span_2_1_subtype', $reportedSpan_2_1->subtype);
        $this->assertNull($reportedSpan_2_1->action);

        $reportedSpan_2_2 = $this->mockEventSink->spanByName('test_span_2_2_name');
        $this->assertSame('test_span_2_2_type', $reportedSpan_2_2->type);
        $this->assertNull($reportedSpan_2_2->subtype);
        $this->assertSame('test_span_2_2_action', $reportedSpan_2_2->action);

        $this->assertThrows(
            NotFoundException::class,
            function () {
                $this->mockEventSink->spanByName('nonexistent_test_span_name');
            }
        );
    }

    public function testTransactionSetName(): void
    {
        // Act
        $tx = $this->tracer->beginTransaction('test_TX_name_1', 'test_TX_type');
        $tx->setName('test_TX_name_2');
        $tx->end();

        // Assert
        $reportedTx = $this->mockEventSink->singleTransaction();
        $this->assertSame('test_TX_name_2', $reportedTx->name);
    }

    public function testTransactionSetType(): void
    {
        // Act
        $tx = $this->tracer->beginTransaction('test_TX_name', 'test_TX_type_1');
        $tx->setType('test_TX_type_2');
        $tx->end();

        // Assert
        $reportedTx = $this->mockEventSink->singleTransaction();
        $this->assertSame('test_TX_type_2', $reportedTx->type);
    }

    public function testTransactionSetResult(): void
    {
        // Act
        $tx = $this->tracer->beginTransaction('test_TX_name', 'test_TX_type_1');
        $this->assertSame(null, $tx->getResult());
        $tx->setResult('test_TX_result');
        $this->assertSame('test_TX_result', $tx->getResult());
        $tx->end();

        // Assert
        $reportedTx = $this->mockEventSink->singleTransaction();
        $this->assertSame('test_TX_result', $reportedTx->result);
    }

    public function testTransactionSetResultToNull(): void
    {
        // Act
        $tx = $this->tracer->beginTransaction('test_TX_name', 'test_TX_type_1');
        $this->assertSame(null, $tx->getResult());
        $tx->setResult('test_TX_result');
        $this->assertSame('test_TX_result', $tx->getResult());
        $tx->setResult(null);
        $this->assertSame(null, $tx->getResult());
        $tx->end();

        // Assert
        $reportedTx = $this->mockEventSink->singleTransaction();
        $this->assertSame(null, $reportedTx->result);
    }

    public function testSpanSetName(): void
    {
        // Act
        $tx = $this->tracer->beginTransaction('test_TX_name', 'test_TX_type');
        $span = $tx->beginChildSpan('test_span_name_1', 'test_span_type');
        $span->setName('test_span_name_2');
        $span->end();
        $tx->end();

        // Assert
        $reportedSpan = $this->mockEventSink->singleSpan();
        $this->assertSame('test_span_name_2', $reportedSpan->name);
    }

    public function testSpanSetType(): void
    {
        // Act
        $tx = $this->tracer->beginTransaction('test_TX_name', 'test_TX_type');
        $span = $tx->beginChildSpan('test_span_name', 'test_span_type_1');
        $span->setType('test_span_type_2');
        $span->end();
        $tx->end();

        // Assert
        $reportedSpan = $this->mockEventSink->singleSpan();
        $this->assertSame('test_span_type_2', $reportedSpan->type);
    }

    public function testSpanSetSubtype(): void
    {
        // Act
        $tx = $this->tracer->beginTransaction('test_TX_name', 'test_TX_type');
        $span = $tx->beginChildSpan('test_span_name', 'test_span_type');
        $span->setSubtype('test_span_subtype');
        $span->end();
        $tx->end();

        // Assert
        $reportedSpan = $this->mockEventSink->singleSpan();
        $this->assertSame('test_span_subtype', $reportedSpan->subtype);
    }

    public function testSpanSetAction(): void
    {
        // Act
        $tx = $this->tracer->beginTransaction('test_TX_name', 'test_TX_type');
        $span = $tx->beginChildSpan('test_span_name', 'test_span_type');
        $span->setAction('test_span_action');
        $span->end();
        $tx->end();

        // Assert
        $reportedSpan = $this->mockEventSink->singleSpan();
        $this->assertSame('test_span_action', $reportedSpan->action);
    }

    public function testGeneratedIds(): void
    {
        // Act
        $tx = $this->tracer->beginTransaction('test_TX_name', 'test_TX_type');
        $span = $tx->beginChildSpan('test_span_name', 'test_span_type');
        $span->end();
        $tx->end();

        // Assert
        $this->assertValidTransactionAndItsSpans(
            $this->mockEventSink->singleTransaction(),
            $this->mockEventSink->idToSpan()
        );
    }

    public function testVersionShouldNotBeEmpty(): void
    {
        $this->assertTrue(strlen(ElasticApm::VERSION) != 0);
    }

    public function testSpanContextDestinationService(): void
    {
        foreach ([true, false] as $shouldSet) {
            $this->mockEventSink->clear();

            // Act
            $tx = $this->tracer->beginTransaction('test_TX_name', 'test_TX_type');
            $span = $tx->beginChildSpan('test_span_name', 'test_span_type');

            if ($shouldSet) {
                $span->context()->destination()->setService(
                    'test_span_destination_service_name',
                    'test_span_destination_service_resource',
                    'test_span_destination_service_type'
                );
            } else {
                // Access context.destination without setting anything to verify that it's sent only when not empty
                $span->context()->destination();
            }

            $span->end();
            $tx->end();

            // Assert
            $spanData = $this->mockEventSink->singleSpan();

            if ($shouldSet) {
                self::assertNotNull($spanData->context);
                self::assertNotNull($spanData->context->destination);
                self::assertNotNull($spanData->context->destination->service);
                self::assertSame('test_span_destination_service_name', $spanData->context->destination->service->name);
                self::assertSame(
                    'test_span_destination_service_resource',
                    $spanData->context->destination->service->resource
                );
                self::assertSame('test_span_destination_service_type', $spanData->context->destination->service->type);
            } else {
                self::assertNull($spanData->context);
            }
        }
    }

    public function testSpanContextDb(): void
    {
        foreach ([true, false] as $shouldSet) {
            $this->mockEventSink->clear();

            // Act
            $tx = $this->tracer->beginTransaction('test_TX_name', 'test_TX_type');
            $span = $tx->beginChildSpan('test_span_name', 'test_span_type');

            if ($shouldSet) {
                $span->context()->db()->setStatement('test span ctx DB statement');
            } else {
                // Access context.db without setting anything to verify that it's sent only when not empty
                $span->context()->db();
            }

            $span->end();
            $tx->end();

            // Assert
            $spanData = $this->mockEventSink->singleSpan();

            if ($shouldSet) {
                self::assertNotNull($spanData->context);
                self::assertNotNull($spanData->context->db);
                self::assertSame('test span ctx DB statement', $spanData->context->db->statement);
            } else {
                self::assertNull($spanData->context);
            }
        }
    }
}
