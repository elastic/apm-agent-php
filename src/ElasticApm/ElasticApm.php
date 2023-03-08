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

    public const VERSION = '1.8.1';

    /**
     * Begins a new transaction and sets it as the current transaction.
     *
     * @param string      $name                      New transaction's name
     * @param string      $type                      New transaction's type
     * @param float|null  $timestamp                 Start time of the new transaction
     * @param string|null $serializedDistTracingData - DEPRECATED since version 1.3 -
     *                                               use newTransaction()->distributedTracingHeaderExtractor() instead
     *
     * @return TransactionInterface New transaction
     *
     * @see TransactionInterface::setName() For the description.
     * @see TransactionInterface::setType() For the description.
     * @see TransactionInterface::getTimestamp() For the description.
     */
    public static function beginCurrentTransaction(
        string $name,
        string $type,
        ?float $timestamp = null,
        ?string $serializedDistTracingData = null
    ): TransactionInterface {
        return GlobalTracerHolder::getValue()
                                 ->beginCurrentTransaction($name, $type, $timestamp, $serializedDistTracingData);
    }

    /**
     * Begins a new transaction, sets as the current transaction,
     * runs the provided callback as the new transaction and automatically ends the new transaction.
     *
     * @template T
     *
     * @param string      $name      New transaction's name
     * @param string      $type      New transaction's type
     * @param Closure(TransactionInterface): T $callback Callback to execute as the new transaction
     * @param float|null  $timestamp Start time of the new transaction
     * @param string|null $serializedDistTracingData - DEPRECATED since version 1.3 -
     *                                               use newTransaction()->distributedTracingHeaderExtractor() instead
     * @return T The return value of $callback
     *
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
        return GlobalTracerHolder::getValue()->captureCurrentTransaction(
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
        return GlobalTracerHolder::getValue()->getCurrentTransaction();
    }

    /**
     * If there is the current span then it returns the current span.
     * Otherwise if there is the current transaction then it returns the current transaction.
     * Otherwise it returns the noop execution segment.
     *
     * @return ExecutionSegmentInterface The current execution segment
     */
    public static function getCurrentExecutionSegment(): ExecutionSegmentInterface
    {
        return GlobalTracerHolder::getValue()->getCurrentExecutionSegment();
    }

    /**
     * Begins a new transaction.
     *
     * @param string      $name      New transaction's name
     * @param string      $type      New transaction's type
     * @param float|null  $timestamp Start time of the new transaction
     * @param string|null $serializedDistTracingData - DEPRECATED since version 1.3 -
     *                                               use newTransaction()->distributedTracingHeaderExtractor() instead
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
        return GlobalTracerHolder::getValue()->beginTransaction($name, $type, $timestamp, $serializedDistTracingData);
    }

    /**
     * Begins a new transaction,
     * runs the provided callback as the new transaction and automatically ends the new transaction.
     *
     * @param string      $name      New transaction's name
     * @param string      $type      New transaction's type
     * @param Closure     $callback  Callback to execute as the new transaction
     * @param float|null  $timestamp Start time of the new transaction
     * @param string|null $serializedDistTracingData - DEPRECATED since version 1.3 -
     *                                               use newTransaction()->distributedTracingHeaderExtractor() instead
     *
     * @return mixed The return value of $callback
     *
     * @template T
     * @phpstan-param Closure(TransactionInterface): T $callback Callback to execute as the new transaction
     * @phpstan-return T The return value of $callback
     *
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
        return GlobalTracerHolder::getValue()->captureTransaction(
            $name,
            $type,
            $callback,
            $timestamp,
            $serializedDistTracingData
        );
    }

    /**
     * Advanced API to begin a new transaction
     *
     * @param string $name New transaction's name
     * @param string $type New transaction's type
     *
     * @return TransactionBuilderInterface New transaction builder
     *
     * @see TransactionInterface::setName() For the description.
     * @see TransactionInterface::setType() For the description.
     *
     */
    public static function newTransaction(string $name, string $type): TransactionBuilderInterface
    {
        return GlobalTracerHolder::getValue()->newTransaction($name, $type);
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
        return GlobalTracerHolder::getValue()->createErrorFromThrowable($throwable);
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
        return GlobalTracerHolder::getValue()->createCustomError($customErrorData);
    }

    /**
     * Pauses recording
     */
    public static function pauseRecording(): void
    {
        GlobalTracerHolder::getValue()->pauseRecording();
    }

    /**
     * Resumes recording
     */
    public static function resumeRecording(): void
    {
        GlobalTracerHolder::getValue()->resumeRecording();
    }

    /**
     * @deprecated      Deprecated since version 1.3 - use injectDistributedTracingHeaders() instead
     * @see             injectDistributedTracingHeaders() Use it instead of this method
     *
     * Returns distributed tracing data for the current span/transaction
     */
    public static function getSerializedCurrentDistributedTracingData(): string
    {
        /** @noinspection PhpDeprecationInspection */
        return GlobalTracerHolder::getValue()->getSerializedCurrentDistributedTracingData();
    }
}
