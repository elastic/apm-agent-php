<?php

declare(strict_types=1);

namespace ElasticApm\Report;

interface ReporterInterface
{
    public function reportTransaction(TransactionDtoInterface $transactionDto): void;
    public function reportSpan(SpanDtoInterface $spanDto): void;

    public function isNoop(): bool;
}
