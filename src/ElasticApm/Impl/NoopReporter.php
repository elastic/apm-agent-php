<?php

declare(strict_types=1);

namespace ElasticApm\Impl;

use ElasticApm\Impl\Util\NoopObjectTrait;
use ElasticApm\SpanInterface;
use ElasticApm\TransactionInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
class NoopReporter implements ReporterInterface
{
    use NoopObjectTrait;

    /** @inheritDoc */
    public function reportTransaction(TransactionInterface $transaction): void
    {
    }

    /** @inheritDoc */
    public function reportSpan(SpanInterface $span): void
    {
    }
}
