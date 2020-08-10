<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Elastic\Apm\Impl\Util\NoopObjectTrait;
use Elastic\Apm\Metadata;
use Elastic\Apm\SpanInterface;
use Elastic\Apm\TransactionInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class NoopEventSink implements EventSinkInterface
{
    use NoopObjectTrait;

    /** @inheritDoc */
    public function setMetadata(Metadata $metadata): void
    {
    }

    /** @inheritDoc */
    public function consumeTransaction(TransactionInterface $transactionTransaction): void
    {
    }

    /** @inheritDoc */
    public function consumeSpan(SpanInterface $span): void
    {
    }
}
