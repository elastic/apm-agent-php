<?php

declare(strict_types=1);

namespace ElasticApm\Impl;

use ElasticApm\NoopTransaction;
use ElasticApm\Report\ReporterInterface;
use ElasticApm\TracerInterface;
use ElasticApm\TransactionInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class Tracer implements TracerInterface
{
    /** @var ReporterInterface */
    private $reporter;

    /** @var TransactionInterface|null */
    private $currentTransaction;

    public function __construct(ReporterInterface $reporter)
    {
        $this->reporter = $reporter;
        $this->currentTransaction = null;
    }

    public function beginTransaction(?string $name, string $type): TransactionInterface
    {
        return new Transaction($this, $name, $type);
    }

    public function getReporter(): ReporterInterface
    {
        return $this->reporter;
    }

    public function isNoop(): bool
    {
        return false;
    }

    public function beginCurrentTransaction(?string $name, string $type): TransactionInterface
    {
        $this->currentTransaction = $this->beginTransaction($name, $type);
        return $this->currentTransaction;
    }

    public function getCurrentTransaction(): TransactionInterface
    {
        return $this->currentTransaction ?? NoopTransaction::create();
    }

    public function resetCurrentTransaction(): void
    {
        $this->currentTransaction = null;
    }
}
