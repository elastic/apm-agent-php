<?php

declare(strict_types=1);

namespace Elastic\Apm;

use Closure;

interface TransactionDataInterface extends ExecutionSegmentDataInterface
{
    /**
     * Hex encoded 64 random bits ID of the parent transaction or span.
     * Only a root transaction of a trace does not have a parent ID, otherwise it needs to be set.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/transactions/transaction.json#L19
     */
    public function getParentId(): ?string;

    /**
     * Number of correlated spans that are recorded.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/transactions/transaction.json#L27
     */
    public function getStartedSpansCount(): int;

    /**
     * Number of spans that have been dropped by the agent recording the transaction.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/transactions/transaction.json#L32
     */
    public function getDroppedSpansCount(): int;

    /**
     * The result of the transaction.
     * For HTTP-related transactions, this should be the status code formatted like 'HTTP 2xx'.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/transactions/transaction.json#L52
     */
    public function getResult(): ?string;

    /**
     * Transactions that are 'sampled' will include all available information
     * Transactions that are not sampled will not have 'spans' or 'context'.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/transactions/transaction.json#L72
     */
    public function isSampled(): bool;
}
