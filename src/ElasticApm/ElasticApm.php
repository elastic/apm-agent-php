<?php

/*
 * Licensed to Elasticsearch B.V. under one or more contributor
 * license agreements. See the NOTICE file distributed with
 * this work for additional information regarding copyright
 * ownership. Elasticsearch B.V. licenses this file to you under
 * the Apache License, Version 2.0 (the "License"); you may
 * not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations
 * under the License.
 */

declare(strict_types=1);

namespace Elastic\Apm;

use Closure;
use Elastic\Apm\Impl\GlobalTracerHolder;
use Elastic\Apm\Impl\Util\StaticClassTrait;
use Throwable;

/**
 * Class ElasticApm is a facade (as in Facade design pattern) to the rest of Elastic APM public API.
 */
final class ElasticApm
{
    use StaticClassTrait;

    /** @var string */
    public const VERSION = '1.1';

    /**
     * Begins a new transaction and sets it as the current transaction.
     *
     * @param string      $name      New transaction's name
     * @param string      $type      New transaction's type
     * @param float|null  $timestamp Start time of the new transaction
     * @param string|null $serializedDistTracingData
     *
     * @return TransactionInterface New transaction
     *
     * @see TransactionInterface::setName() For the description.
     * @see TransactionInterface::setType() For the description.
     * @see TransactionInterface::getTimestamp() For the description.
     *
     */
    public static function beginCurrentTransaction(
        string $name,
        string $type,
        ?float $timestamp = null,
        ?string $serializedDistTracingData = null
    ): TransactionInterface {
        return GlobalTracerHolder::get()->beginCurrentTransaction($name, $type, $timestamp, $serializedDistTracingData);
    }

    /**
     * Begins a new transaction, sets as the current transaction,
     * runs the provided callback as the new transaction and automatically ends the new transaction.
     *
     * @param string      $name      New transaction's name
     * @param string      $type      New transaction's type
     * @param Closure     $callback  Callback to execute as the new transaction
     * @param float|null  $timestamp Start time of the new transaction
     * @param string|null $serializedDistTracingData
     *
     * @return mixed The return value of $callback
     *
     * @template        T
     * @see             TransactionInterface::setName() For the description.
     * @see             TransactionInterface::setType() For the description.
     * @see             TransactionInterface::getTimestamp() For the description.
     */
    public static function captureCurrentTransaction(
        string $name,
        string $type,
        Closure $callback,
        ?float $timestamp = null,
        ?string $serializedDistTracingData = null
    ) {
        return GlobalTracerHolder::get()->captureCurrentTransaction(
            $name,
            $type,
            $callback,
            $timestamp,
            $serializedDistTracingData
        );
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
     * Begins a new transaction.
     *
     * @param string      $name      New transaction's name
     * @param string      $type      New transaction's type
     * @param float|null  $timestamp Start time of the new transaction
     * @param string|null $serializedDistTracingData
     *
     * @return TransactionInterface New transaction
     *
     * @see TransactionInterface::setName() For the description.
     * @see TransactionInterface::setType() For the description.
     * @see TransactionInterface::getTimestamp() For the description.
     */
    public static function beginTransaction(
        string $name,
        string $type,
        ?float $timestamp = null,
        ?string $serializedDistTracingData = null
    ): TransactionInterface {
        return GlobalTracerHolder::get()->beginTransaction($name, $type, $timestamp, $serializedDistTracingData);
    }

    /**
     * Begins a new transaction,
     * runs the provided callback as the new transaction and automatically ends the new transaction.
     *
     * @param string      $name      New transaction's name
     * @param string      $type      New transaction's type
     * @param Closure     $callback  Callback to execute as the new transaction
     * @param float|null  $timestamp Start time of the new transaction
     * @param string|null $serializedDistTracingData
     *
     * @return mixed The return value of $callback
     *
     * @template        T
     * @see             TransactionInterface::setName() For the description.
     * @see             TransactionInterface::setType() For the description.
     * @see             TransactionInterface::getTimestamp() For the description.
     */
    public static function captureTransaction(
        string $name,
        string $type,
        Closure $callback,
        ?float $timestamp = null,
        ?string $serializedDistTracingData = null
    ) {
        return GlobalTracerHolder::get()->captureTransaction(
            $name,
            $type,
            $callback,
            $timestamp,
            $serializedDistTracingData
        );
    }

    /**
     * Creates an error based on the given Throwable instance
     * with the current execution segment (if there is one) as the parent.
     *
     * @param Throwable $throwable
     *
     * @return string|null ID of the reported error event or null if no event was reported
     *                      (for example, because recording is disabled)
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/errors/error.json
     */
    public static function createErrorFromThrowable(Throwable $throwable): ?string
    {
        return GlobalTracerHolder::get()->createErrorFromThrowable($throwable);
    }

    /**
     * Creates an error based on the given data
     * with the current execution segment (if there is one) as the parent.
     *
     * @param CustomErrorData $customErrorData
     *
     * @return string|null ID of the reported error event or null if no event was reported
     *                      (for example, because recording is disabled)
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/errors/error.json
     */
    public static function createCustomError(CustomErrorData $customErrorData): ?string
    {
        return GlobalTracerHolder::get()->createCustomError($customErrorData);
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

    /**
     * Returns distributed tracing data for the current span/transaction
     */
    public static function getSerializedCurrentDistributedTracingData(): string
    {
        return GlobalTracerHolder::get()->getSerializedCurrentDistributedTracingData();
    }
}
