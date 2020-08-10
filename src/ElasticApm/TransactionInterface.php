<?php

declare(strict_types=1);

namespace Elastic\Apm;

use Closure;

interface TransactionInterface extends ExecutionSegmentInterface
{
    /**
     * Hex encoded 64 random bits ID of the parent transaction or span.
     * Only a root transaction of a trace does not have a parent ID, otherwise it needs to be set.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/transactions/transaction.json#L19
     */
    public function getParentId(): ?string;

    /**
     * Begins a new span with the current execution segment
     * (which is the current span if there is one or the current transaction otherwise)
     * as the new span's parent and
     * sets as the new span as the current span for this transaction.
     *
     * @param string      $name      New span's name.
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
    public function beginCurrentSpan(
        string $name,
        string $type,
        ?string $subtype = null,
        ?string $action = null,
        ?float $timestamp = null
    ): SpanInterface;

    /**
     * Begins a new span with the current execution segment as the new span's parent and
     * sets the new span as the current span for this transaction.
     *
     * @param string      $name      New span's name
     * @param string      $type      New span's type
     * @param Closure     $callback  Callback to execute as the new span
     * @param string|null $subtype   New span's subtype
     * @param string|null $action    New span's action
     * @param float|null  $timestamp Start time of the new span
     *
     * @see             SpanInterface::getName() For the description.
     * @see             SpanInterface::getType() For the description.
     * @see             SpanInterface::getSubtype() For the description.
     * @see             SpanInterface::getAction() For the description.
     * @see             SpanInterface::getTimestamp() For the description.
     *
     * @template        T
     * @phpstan-param   Closure(SpanInterface $newSpan): T $callback
     * @phpstan-return  T
     *
     * @return mixed The return value of $callback
     */
    public function captureCurrentSpan(
        string $name,
        string $type,
        Closure $callback,
        ?string $subtype = null,
        ?string $action = null,
        ?float $timestamp = null
    );

    /**
     * Returns the current span.
     *
     * @return SpanInterface The current span
     */
    public function getCurrentSpan(): SpanInterface;

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

    public function context(): TransactionContextInterface;

    public function __toString(): string;
}
