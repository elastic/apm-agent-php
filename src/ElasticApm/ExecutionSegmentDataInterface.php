<?php

declare(strict_types=1);

namespace Elastic\Apm;

/**
 * This interface has functionality shared between Transaction and Span.
 */
interface ExecutionSegmentDataInterface
{
    /**
     * How long the event took to complete.
     * In milliseconds with 3 decimal points.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/transactions/transaction.json#L43
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/spans/span.json#L132
     */
    public function getDuration(): float;

    /**
     * Hex encoded 64 random bits (== 8 bytes == 16 hex digits) ID.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/transactions/transaction.json#L9
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/spans/span.json#L9
     */
    public function getId(): string;

    /**
     * A flat mapping of user-defined labels with string keys and null, string, boolean or number values.
     *
     * The length of a key and a string value is limited to 1024.
     *
     * @return array<string, mixed>
     *
     * @link    https://github.com/elastic/apm-server/blob/7.0/docs/spec/transactions/transaction.json#L40
     * @link    https://github.com/elastic/apm-server/blob/7.0/docs/spec/context.json#L46
     * @link    https://github.com/elastic/apm-server/blob/7.0/docs/spec/spans/span.json#L88
     * @link    https://github.com/elastic/apm-server/blob/7.0/docs/spec/tags.json
     */
    public function getLabels(): array;

    /**
     * - For transactions:
     *      The name of this transaction.
     *      Generic designation of a transaction in the scope of a single service (eg: 'GET /users/:id').
     *
     * - For spans:
     *      Generic designation of a span in the scope of a transaction.
     *
     * The length of this string is limited to 1024.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/transactions/transaction.json#L47
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/spans/span.json#L136
     */
    public function getName(): string;

    /**
     * Recorded time of the event.
     * For events that have non-zero duration this time corresponds to the start of the event.
     * UTC based and in microseconds since Unix epoch.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/transactions/transaction.json#L6
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/spans/span.json#L6
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/timestamp_epoch.json#L7
     */
    public function getTimestamp(): float;

    /**
     * Hex encoded 128 random bits (== 16 bytes == 32 hex digits) ID of the correlated trace.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/transactions/transaction.json#L14
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/spans/span.json#L19
     */
    public function getTraceId(): string;

    /**
     * Keyword of specific relevance in the service's domain
     * e.g.,
     *      - For transaction: 'db', 'external' for a span and 'request', 'backgroundjob' for a transaction, etc.
     *      - For span: 'db.postgresql.query', 'template.erb', etc.
     *
     * The length of this string is limited to 1024.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/transactions/transaction.json#L57
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/spans/span.json#L149
     */
    public function getType(): string;
}
