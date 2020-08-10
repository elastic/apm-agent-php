<?php

declare(strict_types=1);

namespace Elastic\Apm;

use Closure;

/**
 * This interface has functionality shared between Transaction and Span.
 */
interface ExecutionSegmentInterface
{
    /**
     * Begins a new span with this execution segment as the new span's parent.
     *
     * @param string      $name      New span's name
     * @param string      $type      New span's type
     * @param string|null $subtype   New span's subtype
     * @param string|null $action    New span's action
     * @param float|null  $timestamp Start time of the new span
     *
     * @see SpanInterface::getName() For the description.
     * @see SpanInterface::getType() For the description.
     * @see SpanInterface::getSubtype() For the description.
     * @see SpanInterface::getAction() For the description.
     * @see SpanInterface::getTimestamp() For the description.
     *
     * @return SpanInterface New span
     */
    public function beginChildSpan(
        string $name,
        string $type,
        ?string $subtype = null,
        ?string $action = null,
        ?float $timestamp = null
    ): SpanInterface;

    /**
     * Begins a new span with this execution segment as the new span's parent,
     * runs the provided callback as the new span and automatically ends the new span.
     *
     * @see             SpanInterface::getName() For the description.
     * @see             SpanInterface::getType() For the description.
     * @see             SpanInterface::getSubtype() For the description.
     * @see             SpanInterface::getAction() For the description.
     * @see             SpanInterface::getTimestamp() For the description.
     *
     * @param string      $name      New span's name
     * @param string      $type      New span's type
     * @param Closure     $callback  Callback to execute as the new span
     * @param string|null $subtype   New span's subtype
     * @param string|null $action    New span's action
     * @param float|null  $timestamp Start time of the new span
     *
     * @return mixed The return value of $callback
     *
     * @template        T
     * @phpstan-param   Closure(SpanInterface $newSpan): T $callback
     * @phpstan-return  T
     */
    public function captureChildSpan(
        string $name,
        string $type,
        Closure $callback,
        ?string $subtype = null,
        ?string $action = null,
        ?float $timestamp = null
    );

    public function isNoop(): bool;

    /**
     * Hex encoded 64 random bits (== 8 bytes == 16 hex digits) ID.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/transactions/transaction.json#L9
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/spans/span.json#L9
     */
    public function getId(): string;

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
     *
     * @param string $name
     */
    public function setName(string $name): void;

    /**
     * @see setName() For the description
     */
    public function getName(): string;

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
     *
     * @param string $type
     */
    public function setType(string $type): void;

    /**
     * @see setType() For the description
     */
    public function getType(): string;

    public function discard(): void;

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
     * Checks if this execution segment has already ended.
     */
    public function hasEnded(): bool;

    /**
     * How long the event took to complete.
     * In milliseconds with 3 decimal points.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/transactions/transaction.json#L43
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/spans/span.json#L132
     */
    public function getDuration(): float;
}
