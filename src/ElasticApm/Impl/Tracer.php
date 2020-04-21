<?php

/** @noinspection PhpUndefinedClassInspection */

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Closure;
use Elastic\Apm\TracerInterface;
use Elastic\Apm\TransactionInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class Tracer implements TracerInterface
{
    /** @var ClockInterface */
    private $clock;

    /** @var ReporterInterface */
    private $reporter;

    /** @var TransactionInterface|null */
    private $currentTransaction;

    public function __construct(ClockInterface $clock, ReporterInterface $reporter)
    {
        $this->clock = $clock;
        $this->reporter = $reporter;
        $this->currentTransaction = null;
    }

    /** @inheritDoc */
    public function beginTransaction(string $name, string $type): TransactionInterface
    {
        return new Transaction($this, $name, $type);
    }

    /** @inheritDoc */
    public function beginCurrentTransaction(string $name, string $type): TransactionInterface
    {
        $this->currentTransaction = $this->beginTransaction($name, $type);
        return $this->currentTransaction;
    }

    /** @inheritDoc */
    public function captureTransaction(string $name, string $type, Closure $callback)
    {
        $transaction = $this->beginTransaction($name, $type);
        try {
            return $callback($transaction);
        } finally {
            $transaction->end();
        }
    }

    /** @inheritDoc */
    public function captureCurrentTransaction(string $name, string $type, Closure $callback)
    {
        $transaction = $this->beginCurrentTransaction($name, $type);
        try {
            return $callback($transaction);
        } finally {
            $transaction->end();
        }
    }

    public function getClock(): ClockInterface
    {
        return $this->clock;
    }

    public function getReporter(): ReporterInterface
    {
        return $this->reporter;
    }

    public function isNoop(): bool
    {
        return false;
    }

    public function getCurrentTransaction(): TransactionInterface
    {
        return $this->currentTransaction ?? NoopTransaction::instance();
    }

    public function resetCurrentTransaction(): void
    {
        $this->currentTransaction = null;
    }
}
