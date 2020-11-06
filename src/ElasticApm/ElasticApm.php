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
    public const VERSION = '0.3';

    /**
     * Begins a new transaction and sets it as the current transaction.
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
     * Pauses recording
     */
    public static function pauseRecording(): void
    {
        GlobalTracerHolder::get()->pauseRecording();
    }

    /**
     * Resumes recording
     */
    public static function resumeRecording(): void
    {
        GlobalTracerHolder::get()->resumeRecording();
    }
}
