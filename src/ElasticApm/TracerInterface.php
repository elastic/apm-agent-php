<?php

/** @noinspection PhpUndefinedClassInspection */

declare(strict_types=1);

namespace Elastic\Apm;

use Closure;

interface TracerInterface
{
    /**
     * Begins a new transaction.
     *
     * @param string $name New transaction's name
     * @param string $type New transaction's type
     *
     * @return TransactionInterface New transaction
     */
    public function beginTransaction(string $name, string $type): TransactionInterface;

    /**
     * Begins a new transaction, runs the provided callback as the new transaction
     * and automatically ends the new transaction.
     *
     * @param string  $name     New transaction's name
     * @param string  $type     New transaction's type
     * @param Closure $callback Callback to execute as the new transaction
     *
     * @template        T
     * @phpstan-param   Closure(TransactionInterface $newTransaction): T $callback
     * @phpstan-return  T
     *
     * @return mixed The return value of $callback
     */
    public function captureTransaction(string $name, string $type, Closure $callback);

    /**
     * Begins a new transaction and sets the new transaction as the current transaction for this tracer.
     *
     * @param string $name New transaction's name
     * @param string $type New transaction's type
     *
     * @return TransactionInterface New transaction
     */
    public function beginCurrentTransaction(string $name, string $type): TransactionInterface;

    /**
     * Begins a new transaction, sets as the current transaction for this tracer,
     * runs the provided callback as the new transaction and automatically ends the new transaction.
     *
     * @param string  $name     New transaction's name
     * @param string  $type     New transaction's type
     * @param Closure $callback Callback to execute as the new transaction
     *
     * @template        T
     * @phpstan-param   Closure(TransactionInterface $newTransaction): T $callback
     * @phpstan-return  T
     *
     * @return mixed The return value of $callback
     */
    public function captureCurrentTransaction(string $name, string $type, Closure $callback);

    public function getCurrentTransaction(): TransactionInterface;

    public function isNoop(): bool;
}
