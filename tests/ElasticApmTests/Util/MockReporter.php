<?php

declare(strict_types=1);

namespace ElasticApmTests\Util;

use ElasticApm\Report\ReporterInterface;
use ElasticApm\Report\SpanDtoInterface;
use ElasticApm\Report\TransactionDtoInterface;

class MockReporter implements ReporterInterface
{
    /** @var TransactionDtoInterface[] */
    private $transactions;

    /** @var SpanDtoInterface[] */
    private $spans;

    public function __construct()
    {
        $this->transactions = [];
        $this->spans = [];
    }

    public function reportTransaction(TransactionDtoInterface $transactionDto): void
    {
        $this->transactions[] = $transactionDto;
    }

    /** @return TransactionDtoInterface[] */
    public function getTransactions(): array
    {
        return $this->transactions;
    }

    public function reportSpan(SpanDtoInterface $spanDto): void
    {
        $this->spans[] = $spanDto;
    }

    /** @return SpanDtoInterface[] */
    public function getSpans(): array
    {
        return $this->spans;
    }

    /**
     * @param string $name
     *
     * @return SpanDtoInterface
     * @throws NotFoundException
     */
    public function getSpanByName(string $name): SpanDtoInterface
    {
        $index = ArrayUtil::findIndexByPredicate(
            $this->spans,
            function (SpanDtoInterface $s) use ($name): bool {
                return $s->getName() === $name;
            }
        );
        if ($index == -1) {
            throw new NotFoundException("Span with the name `$name' not found");
        }
        return $this->spans[$index];
    }

    public function isNoop(): bool
    {
        return false;
    }
}
