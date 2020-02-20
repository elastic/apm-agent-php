<?php

declare(strict_types=1);

namespace ElasticApm;

/**
 * This interface has functionality shared between Transaction and Span.
 */
interface ExecutionSegmentInterface
{
    public function beginChildSpan(
        string $name,
        string $type,
        ?string $subtype = null,
        ?string $action = null
    ): SpanInterface;

    /**
     * Sets the end timestamp and finalizes this object's state.
     *
     * With the exception of calls to getContext() (which are always allowed),
     * end() must be the last call made to any object of this type,
     * and to do otherwise leads to undefined behavior but not throwing an exception.
     *
     * If end() was already called for this object then a warning should be logged.
     *
     * @param float|null $duration In milliseconds with 3 decimal points.
     */
    public function end(?float $duration = null): void;

    /**
     * Recorded time of the event.
     * For events that have non-zero duration this time corresponds to the start of the event.
     * UTC based and in microseconds since Unix epoch.
     *
     * @link https://github.com/elastic/apm-server/blob/6.5/docs/spec/timestamp_epoch.json#L7
     */
    public function getTimestamp(): float;

    /**
     * How long the event took to complete.
     * In milliseconds with 3 decimal points.
     *
     * @link https://github.com/elastic/apm-server/blob/6.5/docs/spec/transactions/common_transaction.json#L11
     * @link https://github.com/elastic/apm-server/blob/6.5/docs/spec/spans/common_span.json#L55
     */
    public function getDuration(): float;

    /**
     * Hex encoded 64 random bits (== 8 bytes == 16 hex digits) ID.
     *
     * @link https://github.com/elastic/apm-server/blob/6.5/docs/spec/transactions/v2_transaction.json#L10
     * @link https://github.com/elastic/apm-server/blob/6.5/docs/spec/spans/v2_span.json#L10
     */
    public function getId(): string;

    /**
     * Hex encoded 128 random bits (== 16 bytes == 32 hex digits) ID of the correlated trace.
     *
     * @link https://github.com/elastic/apm-server/blob/6.5/docs/spec/transactions/v2_transaction.json#L15
     * @link https://github.com/elastic/apm-server/blob/6.5/docs/spec/spans/v2_span.json#L20
     */
    public function getTraceId(): string;

    /**
     * Keyword of specific relevance in the service's domain
     * e.g., 'db', 'external' for a span and 'request', 'backgroundjob' for a transaction, etc.
     *
     * @link https://github.com/elastic/apm-server/blob/6.5/docs/spec/spans/common_span.json#L72
     */
    public function getType(): string;

    /**
     * @param string $type
     *
     * @see getType() For the description
     */
    public function setType(string $type): void;

    /**
     * Apm Server 6.5: A flat mapping of user-defined labels with string values.
     * Apm Server 6.7+: A flat mapping of user-defined labels with string, boolean or number values.
     *
     * @param string $key
     * @param null   $default
     *
     * @return string|bool|int|float|null
     *
     * @link https://github.com/elastic/apm-server/blob/6.5/docs/spec/tags.json
     * @link https://github.com/elastic/apm-server/blob/6.7/docs/spec/tags.json
     */
    public function getLabel(string $key, $default = null);

    /**
     * @param string                     $key
     * @param string|bool|int|float|null $value
     *
     * @see getLabel() For the description
     */
    public function setLabel(string $key, $value): void;

    public function isNoop(): bool;
}
