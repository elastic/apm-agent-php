<?php

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
