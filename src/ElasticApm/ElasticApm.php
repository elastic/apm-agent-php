<?php

/** @noinspection PhpUndefinedClassInspection */

declare(strict_types=1);

namespace Elastic\Apm;

use Closure;
use Elastic\Apm\Impl\GlobalTracerHolder;
use Elastic\Apm\Impl\Util\StaticClassTrait;

/**
 * Class ElasticApm is a facade (as in Facade design pattern) to the rest of Elastic APM public API.
 */
final class ElasticApm
{
    use StaticClassTrait;

    /** @var string */
    public const VERSION = '0.1-preview';

    /**
     * Begins a new transaction and sets as the current transaction.
     *
     * @param string     $name      New transaction's name
     * @param string     $type      New transaction's type
     * @param float|null $timestamp Start time of the new transaction
     *
     * @see TransactionInterface::getName() For the description.
     * @see TransactionInterface::getType() For the description.
     * @see TransactionInterface::getTimestamp() For the description.
     *
     * @return TransactionInterface New transaction
     */
    public static function beginCurrentTransaction(
        string $name,
        string $type,
        ?float $timestamp = null
    ): TransactionInterface {
        return GlobalTracerHolder::get()->beginCurrentTransaction($name, $type, $timestamp);
    }

    /**
     * Begins a new transaction, sets as the current transaction,
     * runs the provided callback as the new transaction and automatically ends the new transaction.
     *
     * @param string     $name      New transaction's name
     * @param string     $type      New transaction's type
     * @param Closure    $callback  Callback to execute as the new transaction
     * @param float|null $timestamp Start time of the new transaction
     *
     * @template        T
     * @phpstan-param   Closure(TransactionInterface $newTransaction): T $callback
     * @phpstan-return  T
     *
     * @see             TransactionInterface::getName() For the description.
     * @see             TransactionInterface::getType() For the description.
     * @see             TransactionInterface::getTimestamp() For the description.
     *
     * @return mixed The return value of $callback
     */
    public static function captureCurrentTransaction(
        string $name,
        string $type,
        Closure $callback,
        ?float $timestamp = null
    ) {
        return GlobalTracerHolder::get()->captureCurrentTransaction($name, $type, $callback, $timestamp);
    }

    /**
     * Returns the current transaction.
     *
     * @return TransactionInterface The current transaction
     */
    public static function getCurrentTransaction(): TransactionInterface
    {
        return GlobalTracerHolder::get()->getCurrentTransaction();
    }

    /**
     * Begins a new span with the current execution segment as the new span's parent and
     * sets as the new span as the current span for this transaction.
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
    public static function beginCurrentSpan(
        string $name,
        string $type,
        ?string $subtype = null,
        ?string $action = null,
        ?float $timestamp = null
    ): SpanInterface {
        return self::getCurrentTransaction()->beginCurrentSpan($name, $type, $subtype, $action, $timestamp);
    }

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
    public static function captureCurrentSpan(
        string $name,
        string $type,
        Closure $callback,
        ?string $subtype = null,
        ?string $action = null,
        ?float $timestamp = null
    ) {
        return self::getCurrentTransaction()->captureCurrentSpan(
            $name,
            $type,
            $callback,
            $subtype,
            $action,
            $timestamp
        );
    }

    /**
     * Returns the current span.
     *
     * @return SpanInterface The current span
     */
    public static function getCurrentSpan(): SpanInterface
    {
        return self::getCurrentTransaction()->getCurrentSpan();
    }
}
