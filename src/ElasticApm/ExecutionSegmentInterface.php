<?php

/** @noinspection PhpUndefinedClassInspection */

declare(strict_types=1);

namespace Elastic\Apm;

use Closure;

/**
 * This interface has functionality shared between Transaction and Span.
 */
interface ExecutionSegmentInterface extends ExecutionSegmentDataInterface
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
    public function captureChildSpan(
        string $name,
        string $type,
        Closure $callback,
        ?string $subtype = null,
        ?string $action = null,
        ?float $timestamp = null
    );

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
     * @param string                     $key
     * @param string|bool|int|float|null $value
     *
     * @see ExecutionSegmentDataInterface::getLabels() For the description
     */
    public function setLabel(string $key, $value): void;
    /**
     * @param string $name
     *
     * @see ExecutionSegmentDataInterface::getName() For the description
     */
    public function setName(string $name): void;

    /**
     * @param string $type
     *
     * @see ExecutionSegmentDataInterface::getType() For the description
     */
    public function setType(string $type): void;

    public function isNoop(): bool;

    public function discard(): void;
}
