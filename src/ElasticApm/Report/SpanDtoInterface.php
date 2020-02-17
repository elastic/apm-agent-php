<?php

declare(strict_types=1);

namespace ElasticApm\Report;

interface SpanDtoInterface extends ExecutionSegmentDtoInterface
{
    /**
     * Hex encoded 64 random bits ID of the correlated transaction.
     *
     * @link https://github.com/elastic/apm-server/blob/6.5/docs/spec/spans/v2_span.json#L15
     */
    public function getTransactionId(): string;

    /**
     * @param string $transactionId
     *
     * @see getTransactionId() For the description
     */
    public function setTransactionId(string $transactionId): void;

    /**
     * Hex encoded 64 random bits ID of the parent.
     * If this span is the root span of the correlated transaction the its parent is the correlated transaction
     * otherwise its parent is the parent span.
     *
     * @link https://github.com/elastic/apm-server/blob/6.5/docs/spec/spans/v2_span.json#L25
     */
    public function getParentId(): string;

    /**
     * @param string $parentId
     *
     * @see getParentId() For the description
     */
    public function setParentId(string $parentId): void;

    /**
     * Offset relative to the transaction's timestamp identifying the start of the span.
     * In milliseconds.
     *
     * https://github.com/elastic/apm-server/blob/6.5/docs/spec/spans/v2_span.json#L30
     */
    public function getStart(): ?int;

    /**
     * @param int|null $start
     *
     * @see getStart() For the description
     */
    public function setStart(?int $start): void;

    /**
     * Generic designation of a span in the scope of a transaction
     *
     * @link https://github.com/elastic/apm-server/blob/6.5/docs/spec/spans/common_span.json#L59
     */
    public function getName(): string;

    /**
     * @param string $name
     *
     * @see getName() For the description
     */
    public function setName(string $name): void;

    /**
     * A further sub-division of the type
     * e.g., 'mysql' for type 'db', 'http' for type 'external', etc.
     *
     * @link https://github.com/elastic/apm-server/blob/6.6/docs/spec/spans/v2_span.json#L34
     */
    public function getSubtype(): ?string;

    /**
     * @param string|null $subtype
     *
     * @see getSubtype() For the description
     */
    public function setSubtype(?string $subtype): void;

    /**
     * The specific kind of event within the sub-type represented by the span
     * e.g., 'query' for type/sub-type 'db'/'mysql'
     *
     * @link https://github.com/elastic/apm-server/blob/6.6/docs/spec/spans/v2_span.json#L39
     */
    public function getAction(): ?string;

    /**
     * @param string|null $action
     *
     * @see getAction() For the description
     */
    public function setAction(?string $action): void;
}
