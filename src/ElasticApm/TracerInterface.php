<?php

declare(strict_types=1);

namespace ElasticApm;

interface TracerInterface
{
    public function beginTransaction(?string $name, string $type): TransactionInterface;

    public function beginCurrentTransaction(?string $name, string $type): TransactionInterface;
    public function getCurrentTransaction(): TransactionInterface;

    public function isNoop(): bool;
}
