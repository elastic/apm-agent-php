<?php

/** @noinspection PhpUndefinedClassInspection */

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Closure;
use Elastic\Apm\Impl\Util\NoopObjectTrait;
use Elastic\Apm\TransactionInterface;
use Throwable;

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
    public function createError(Throwable $throwable): ?string
    {
        return null;
    }

    /** @inheritDoc */
    public function getCurrentTransaction(): TransactionInterface
    {
        return NoopTransaction::singletonInstance();
    }

    /** @inheritDoc */
    public function pauseRecording(): void
    {
    }

    /** @inheritDoc */
    public function resumeRecording(): void
    {
    }

    /** @inheritDoc */
    public function isRecording(): bool
    {
        return false;
    }

    /** @inheritDoc */
    public function setAgentEphemeralId(?string $ephemeralId): void
    {
    }
}
