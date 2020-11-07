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
    /**
     * @param Metadata             $metadata
     * @param SpanData[]           $spansData
     * @param TransactionData|null $transactionData
     */
    public function consume(Metadata $metadata, array $spansData, ?TransactionData $transactionData): void;
}
