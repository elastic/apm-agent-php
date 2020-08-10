<?php

declare(strict_types=1);

namespace Elastic\Apm;

interface SpanInterface extends ExecutionSegmentInterface
{
    /**
     * Hex encoded 64 random bits ID of the correlated transaction.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/spans/span.json#L14
     */
    public function getTransactionId(): string;

    /**
     * Hex encoded 64 random bits ID of the parent.
     * If this span is the root span of the correlated transaction the its parent is the correlated transaction
     * otherwise its parent is the parent span.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/spans/span.json#L24
     */
    public function getParentId(): string;

    /**
     * Offset relative to the transaction's timestamp identifying the start of the span.
     * In milliseconds.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/spans/span.json#L29
     */
    public function getStart(): float;

    /**
     * @param string|null $subtype
     *
     * @see SpanInterface::getSubtype() For the description
     */
    public function setSubtype(?string $subtype): void;

    /**
     * A further sub-division of the type
     * e.g., 'mysql', 'postgresql' or 'elasticsearch' for type 'db', 'http' for type 'external', etc.
     *
     * The length of this string is limited to 1024.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/spans/span.json#L33
     */
    public function getSubtype(): ?string;

    /**
     * @param string|null $action
     *
     * @see SpanInterface::getAction() For the description
     */
    public function setAction(?string $action): void;

    /**
     * The specific kind of event within the sub-type represented by the span
     * e.g., 'query' for type/sub-type 'db'/'mysql', 'connect' for type/sub-type 'db'/'cassandra'
     *
     * The length of this string is limited to 1024.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/spans/span.json#L38
     */
    public function getAction(): ?string;

    public function context(): SpanContextInterface;
}
