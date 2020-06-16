<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Elastic\Apm\Impl\Util\NoopObjectTrait;
use Elastic\Apm\SpanDataInterface;
use Elastic\Apm\TransactionDataInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class NoopEventSink implements EventSinkInterface
{
    use NoopObjectTrait;

    /** @inheritDoc */
    public function setMetadata(MetadataInterface $metadata): void
    {
    }

    /** @inheritDoc */
    public function consumeTransactionData(TransactionDataInterface $transactionTransactionData): void
    {
    }

    /** @inheritDoc */
    public function consumeSpanData(SpanDataInterface $spanSpanData): void
    {
    }
}
