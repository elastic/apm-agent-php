<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
interface EventSinkInterface
{
    public function setMetadata(Metadata $metadata): void;

    /**
     * @param SpanData[]           $spansData
     * @param TransactionData|null $transactionData
     */
    public function consume(array $spansData, ?TransactionData $transactionData): void;
}
