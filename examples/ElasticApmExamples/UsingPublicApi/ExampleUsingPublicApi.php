<?php

/** @noinspection PhpInternalEntityUsedInspection */

declare(strict_types=1);

namespace Elastic\Apm\Examples\UsingPublicApi;

use Elastic\Apm\Impl\TracerBuilder;
use Elastic\Apm\Tests\UnitTests\Util\MockEventSink;
use Elastic\Apm\Tests\UnitTests\Util\UnitTestCaseBase;
use Elastic\Apm\TransactionInterface;

class ExampleUsingPublicApi extends UnitTestCaseBase
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
        $this->assertEmpty($mockEventSink->getIdToSpan());
        $reportedTx = $mockEventSink->getSingleTransaction();
        $this->assertSame('test_TX_name', $reportedTx->getName());
        $this->assertSame('test_TX_type', $reportedTx->getType());

        echo 'Completed successfully' . PHP_EOL;
    }
}
