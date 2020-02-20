<?php

/** @noinspection PhpUndefinedClassInspection */

declare(strict_types=1);

namespace ElasticApm;

use Closure;
use ElasticApm\Impl\TracerSingleton;
use ElasticApm\Impl\Util\StaticClassTrait;

final class ElasticApm
{
    use StaticClassTrait;

    public const VERSION = '0.1-preview';

    /**
     * Begins a new transaction and sets as the current transaction.
     *
     * @param string|null $name New transaction's name
     * @param string      $type New transaction's type
     *
     * @return TransactionInterface New transaction
     */
    public static function beginCurrentTransaction(?string $name, string $type): TransactionInterface
    {
        return TracerSingleton::get()->beginCurrentTransaction($name, $type);
    }

    /**
     * Begins a new transaction, sets as the current transaction,
     * runs the provided callback as the new transaction and automatically ends the new transaction.
     *
     * @param string|null $name     New transaction's name
     * @param string      $type     New transaction's type
     * @param Closure     $callback Callback to execute as the new transaction
     *
     * @template        T
     * @phpstan-param   Closure(TransactionInterface $newTransaction): T $callback
     * @phpstan-return  T
     *
     * @return mixed The return value of $callback
     */
    public static function captureCurrentTransaction(
        ?string $name,
        string $type,
        Closure $callback
    ) {
        return TracerSingleton::get()->captureCurrentTransaction($name, $type, $callback);
    }

    /**
     * Returns the current transaction.
     *
     * @return TransactionInterface The current transaction
     */
    public static function getCurrentTransaction(): TransactionInterface
    {
        return TracerSingleton::get()->getCurrentTransaction();
    }

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
    public static function beginCurrentSpan(
        string $name,
        string $type,
        ?string $subtype = null,
        ?string $action = null
    ): SpanInterface {
        return self::getCurrentTransaction()->beginCurrentSpan($name, $type, $subtype, $action);
    }

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
    public static function captureCurrentSpan(
        string $name,
        string $type,
        Closure $callback,
        ?string $subtype = null,
        ?string $action = null
    ) {
        return self::getCurrentTransaction()->captureCurrentSpan($name, $type, $callback, $subtype, $action);
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
