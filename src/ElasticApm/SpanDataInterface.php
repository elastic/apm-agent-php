<?php

declare(strict_types=1);

namespace Elastic\Apm;

interface SpanDataInterface extends ExecutionSegmentDataInterface
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
     * A further sub-division of the type
     * e.g., 'mysql', 'postgresql' or 'elasticsearch' for type 'db', 'http' for type 'external', etc.
     *
     * The length of this string is limited to 1024.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/spans/span.json#L33
     */
    public function getSubtype(): ?string;

    /**
     * The specific kind of event within the sub-type represented by the span
     * e.g., 'query' for type/sub-type 'db'/'mysql', 'connect' for type/sub-type 'db'/'cassandra'
     *
     * The length of this string is limited to 1024.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/spans/span.json#L38
     */
    public function getAction(): ?string;

    /**
     * List of stack frames with variable attributes (eg: lineno, filename, etc.)
     *
     * @return StacktraceFrame[]|null
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/spans/span.json#L38
     */
    public function getStacktrace(): ?array;
}
