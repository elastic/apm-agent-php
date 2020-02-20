<?php

declare(strict_types=1);

namespace ElasticApmTests\Util;

use ElasticApm\Impl\ReporterInterface;
use ElasticApm\SpanInterface;
use ElasticApm\TransactionInterface;

class MockReporter implements ReporterInterface
{
    /** @var TestCaseBase */
    private $testCaseBase;

    /** @var TransactionInterface[] */
    private $transactions = [];

    /** @var SpanInterface[] */
    private $spans = [];

    public function __construct(TestCaseBase $testCaseBase)
    {
        $this->testCaseBase = $testCaseBase;
    }

    public function reportTransaction(TransactionInterface $transaction): void
    {
        $this->testCaseBase->assertValidTransaction($transaction);
        $this->transactions[] = $transaction;
    }

    /** @return TransactionInterface[] */
    public function getTransactions(): array
    {
        return $this->transactions;
    }

    public function reportSpan(SpanInterface $span): void
    {
        $this->testCaseBase->assertValidSpan($span);
        $this->spans[] = $span;
    }

    /** @return SpanInterface[] */
    public function getSpans(): array
    {
        return $this->spans;
    }

    /**
     * @param string $name
     *
     * @return SpanInterface
     * @throws NotFoundException
     */
    public function getSpanByName(string $name): SpanInterface
    {
        $index = ArrayUtil::findIndexByPredicate(
            $this->spans,
            function (SpanInterface $s) use ($name): bool {
                return $s->getName() === $name;
            }
        );
        if ($index === -1) {
            throw new NotFoundException("Span with the name `$name' not found");
        }
        return $this->spans[$index];
    }

    /**
     * @param TransactionInterface $transaction
     *
     * @return array<SpanInterface>
     */
    public function getSpansForTransaction(TransactionInterface $transaction): array
    {
        return array_filter(
            $this->spans,
            function (SpanInterface $s) use ($transaction): bool {
                return $s->getTransactionId() === $transaction->getId();
            }
        );
    }
}
