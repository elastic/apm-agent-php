<?php

declare(strict_types=1);

namespace ElasticApm\Report;

interface ExecutionSegmentDtoInterface extends TimedEventDtoInterface
{
    /**
     * Hex encoded 64 random bits (== 8 bytes == 16 hex digits) ID.
     *
     * @link https://github.com/elastic/apm-server/blob/6.5/docs/spec/transactions/v2_transaction.json#L10
     * @link https://github.com/elastic/apm-server/blob/6.5/docs/spec/spans/v2_span.json#L10
     */
    public function getId(): string;

    /**
     * @param string $id
     *
     * @see getId() For the description
     */
    public function setId(string $id): void;

    /**
     * Hex encoded 128 random bits (== 16 bytes == 32 hex digits) ID of the correlated trace.
     *
     * @link https://github.com/elastic/apm-server/blob/6.5/docs/spec/transactions/v2_transaction.json#L15
     * @link https://github.com/elastic/apm-server/blob/6.5/docs/spec/spans/v2_span.json#L20
     */
    public function getTraceId(): string;

    /**
     * @param string $traceId
     *
     * @see getTraceId() For the description
     */
    public function setTraceId(string $traceId): void;

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
     * Apm Server 6.5: A flat mapping of user-defined tags with string values.
     * Apm Server 6.7+: A flat mapping of user-defined tags with string, boolean or number values.
     *
     * @param string $key
     * @param null   $default
     *
     * @return string|bool|int|float|null
     *
     * @link https://github.com/elastic/apm-server/blob/6.5/docs/spec/tags.json
     * @link https://github.com/elastic/apm-server/blob/6.7/docs/spec/tags.json
     */
    public function getTag(string $key, $default = null);

    /**
     * @param string $key
     * @param string|bool|int|float|null $value
     *
     * @see getTag() For the description
     */
    public function setTag(string $key, $value): void;
}
