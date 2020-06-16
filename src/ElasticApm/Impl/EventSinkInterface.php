<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Elastic\Apm\SpanDataInterface;
use Elastic\Apm\TransactionDataInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
interface EventSinkInterface
{
    public function setMetadata(MetadataInterface $metadata): void;

    public function consumeTransactionData(TransactionDataInterface $transactionData): void;

    public function consumeSpanData(SpanDataInterface $spanSpanData): void;
}
