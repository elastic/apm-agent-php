<?php

declare(strict_types=1);

namespace ElasticApm;

interface TransactionInterface extends ExecutionSegmentInterface
{
    public function beginCurrentSpan(
        string $name,
        string $type,
        ?string $subtype = null,
        ?string $action = null
    ): SpanInterface;

    public function getCurrentSpan(): SpanInterface;

    /**
     * Hex encoded 64 random bits ID of the parent transaction or span.
     * Only a root transaction of a trace does not have a parent ID, otherwise it needs to be set.
     *
     * @link https://github.com/elastic/apm-server/blob/6.5/docs/spec/transactions/v2_transaction.json#L20
     */
    public function getParentId(): ?string;

    /**
     * The name of this transaction.
     *
     * @link https://github.com/elastic/apm-server/blob/6.5/docs/spec/transactions/common_transaction.json#L13
     */
    public function getName(): ?string;

    /**
     * @param string|null $name
     *
     * @see getName() For the description
     */
    public function setName(?string $name): void;
}
