<?php

/** @noinspection PhpInternalEntityUsedInspection */

declare(strict_types=1);

namespace Elastic\Apm\Examples\UsingPublicApi;

use Elastic\Apm\Impl\TracerBuilder;
use Elastic\Apm\TransactionInterface;
use Elastic\Apm\Tests\Util\MockReporter;
use Elastic\Apm\Tests\Util\TestCaseBase;

class ExampleUsingPublicApi extends TestCaseBase
{
    public function main(): void
    {
        // Arrange
        $mockReporter = new MockReporter($this);
        $tracer = TracerBuilder::startNew()->withReporter($mockReporter)->build();

        // Act
        $tx = $tracer->beginTransaction('test_TX_name', 'test_TX_type');
        $tx->end();

        // Assert
        $this->assertSame(0, count($mockReporter->getSpans()));
        $this->assertSame(1, count($mockReporter->getTransactions()));
        /** @var TransactionInterface $reportedTx */
        $reportedTx = $mockReporter->getTransactions()[0];
        $this->assertSame('test_TX_name', $reportedTx->getName());
        $this->assertSame('test_TX_type', $reportedTx->getType());

        echo 'Completed successfully' . PHP_EOL;
    }
}
