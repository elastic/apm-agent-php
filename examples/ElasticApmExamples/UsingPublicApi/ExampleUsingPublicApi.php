<?php

declare(strict_types=1);

namespace ElasticApmExamples\UsingPublicApi;

use ElasticApm\Impl\Tracer;
use ElasticApm\Report\TransactionDtoInterface;
use ElasticApmTests\Util\MockReporter;
use PHPUnit\Framework\ExpectationFailedException;

class ExampleUsingPublicApi
{
    public function main(): void
    {
        // Arrange
        $mockReporter = new MockReporter();
        $tracer = new Tracer($mockReporter);

        // Act
        $tx = $tracer->beginTransaction('test_TX_name', 'test_TX_type');
        $tx->end();

        // Assert
        $this->assertSame(0, count($mockReporter->getSpans()));
        $this->assertSame(1, count($mockReporter->getTransactions()));
        /** @var TransactionDtoInterface $reportedTx */
        $reportedTx = $mockReporter->getTransactions()[0];
        $this->assertSame('test_TX_name', $reportedTx->getName());
        $this->assertSame('test_TX_type', $reportedTx->getType());

        echo 'Completed successfully' . PHP_EOL;
    }

    /**
     * Asserts that two variables have the same type and value.
     * Used on objects, it asserts that two variables reference
     * the same object.
     *
     * @param int|string|null $expected
     * @param int|string|null $actual
     *
     * @psalm-template ExpectedType
     * @psalm-param    ExpectedType $expected
     * @psalm-assert   =ExpectedType $actual
     *
     * @throws ExpectationFailedException
     */
    private static function assertSame($expected, $actual): void
    {
        if ($expected !== $actual) {
            throw new ExpectationFailedException("$expected is not the same as the $actual.");
        }
    }
}
