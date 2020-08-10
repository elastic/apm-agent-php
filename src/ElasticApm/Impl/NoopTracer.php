<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Closure;
use Elastic\Apm\Impl\Util\NoopObjectTrait;
use Elastic\Apm\TransactionInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class NoopTracer implements TracerInterface
{
    use NoopObjectTrait;

    /** @inheritDoc */
    public function beginTransaction(?string $name, string $type, ?float $timestamp = null): TransactionInterface
    {
        return NoopTransaction::singletonInstance();
    }

    /** @inheritDoc */
    public function beginCurrentTransaction(?string $name, string $type, ?float $timestamp = null): TransactionInterface
    {
        return NoopTransaction::singletonInstance();
    }

    /** @inheritDoc */
    public function captureTransaction(?string $name, string $type, Closure $callback, ?float $timestamp = null)
    {
        return $callback(NoopTransaction::singletonInstance());
    }

    /** @inheritDoc */
    public function captureCurrentTransaction(?string $name, string $type, Closure $callback, ?float $timestamp = null)
    {
        return $callback(NoopTransaction::singletonInstance());
    }

    /** @inheritDoc */
    public function getCurrentTransaction(): TransactionInterface
    {
        return NoopTransaction::singletonInstance();
    }

    public function pauseRecording(): void
    {
    }

    public function resumeRecording(): void
    {
    }

    public function isRecording(): bool
    {
        return false;
    }

    public function __toString(): string
    {
        return 'NO-OP Tracer';
    }
}
