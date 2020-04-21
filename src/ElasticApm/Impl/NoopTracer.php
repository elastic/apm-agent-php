<?php

/** @noinspection PhpUndefinedClassInspection */

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Closure;
use Elastic\Apm\Impl\Util\NoopObjectTrait;
use Elastic\Apm\TracerInterface;
use Elastic\Apm\TransactionInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
class NoopTracer implements TracerInterface
{
    use NoopObjectTrait;

    /** @inheritDoc */
    public function beginTransaction(?string $name, string $type): TransactionInterface
    {
        return NoopTransaction::instance();
    }

    /** @inheritDoc */
    public function beginCurrentTransaction(?string $name, string $type): TransactionInterface
    {
        return NoopTransaction::instance();
    }

    /** @inheritDoc */
    public function captureTransaction(?string $name, string $type, Closure $callback)
    {
        return $callback(NoopTransaction::instance());
    }

    /** @inheritDoc */
    public function captureCurrentTransaction(?string $name, string $type, Closure $callback)
    {
        return $callback(NoopTransaction::instance());
    }

    /** @inheritDoc */
    public function getCurrentTransaction(): TransactionInterface
    {
        return NoopTransaction::instance();
    }
}
