<?php

/** @noinspection PhpUndefinedClassInspection */

declare(strict_types=1);

namespace Elastic\Apm;

use Closure;

interface TransactionInterface extends ExecutionSegmentInterface
{
    /**
     * Begins a new span with the current execution segment as the new span's parent and
     * sets as the new span as the current span for this transaction.
     *
     * @param string      $name    New span's name
     * @param string      $type    New span's type
     * @param string|null $subtype New span's subtype
     * @param string|null $action  New span's action
     *
     * @return SpanInterface New span
     */
    public function beginCurrentSpan(
        string $name,
        string $type,
        ?string $subtype = null,
        ?string $action = null
    ): SpanInterface;

    /**
     * Begins a new span with the current execution segment as the new span's parent and
     * sets the new span as the current span for this transaction.
     *
     * @param string      $name     New span's name
     * @param string      $type     New span's type
     * @param Closure     $callback Callback to execute as the new span
     * @param string|null $subtype  New span's subtype
     * @param string|null $action   New span's action
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
        ?string $action = null
    );

    public function getCurrentSpan(): SpanInterface;

    /**
     * Hex encoded 64 random bits ID of the parent transaction or span.
     * Only a root transaction of a trace does not have a parent ID, otherwise it needs to be set.
     *
     * @link https://github.com/elastic/apm-server/blob/6.5/docs/spec/transactions/v2_transaction.json#L20
     */
    public function getParentId(): ?string;

    /**
     * The name of this transaction.
     *
     * @link https://github.com/elastic/apm-server/blob/6.5/docs/spec/transactions/common_transaction.json#L13
     */
    public function getName(): ?string;

    /**
     * @param string $name
     *
     * @see getName() For the description
     */
    public function setName(string $name): void;
}
