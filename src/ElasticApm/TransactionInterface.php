<?php

/** @noinspection PhpUndefinedClassInspection */

declare(strict_types=1);

namespace Elastic\Apm;

use Closure;

interface TransactionInterface extends ExecutionSegmentInterface, TransactionDataInterface
{
    /**
     * Begins a new span with the current execution segment as the new span's parent and
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
     * @param string|null $result
     *
     * @see TransactionDataInterface::getResult() For the description
     */
    public function setResult(?string $result): void;

    public function __toString(): string;
}
