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
use Throwable;

/**
 * This interface has functionality shared between Transaction and Span.
 */
interface ExecutionSegmentInterface
{
    /**
     * Hex encoded 64 random bits (== 8 bytes == 16 hex digits) ID.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/transactions/transaction.json#L9
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/spans/span.json#L9
     */
    public function getId(): string;

    /**
     * Hex encoded 128 random bits (== 16 bytes == 32 hex digits) ID of the correlated trace.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/transactions/transaction.json#L14
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/spans/span.json#L19
     */
    public function getTraceId(): string;

    /**
     * Recorded time of the event.
     * For events that have non-zero duration this time corresponds to the start of the event.
     * UTC based and in microseconds since Unix epoch.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/transactions/transaction.json#L6
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/spans/span.json#L6
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/timestamp_epoch.json#L7
     */
    public function getTimestamp(): float;

    /**
     * Begins a new span with this execution segment as the new span's parent.
     *
     * @param string      $name      New span's name
     * @param string      $type      New span's type
     * @param string|null $subtype   New span's subtype
     * @param string|null $action    New span's action
     * @param float|null  $timestamp Start time of the new span
     *
     * @see SpanInterface::setName() For the description.
     * @see SpanInterface::setType() For the description.
     * @see SpanInterface::setSubtype() For the description.
     * @see SpanInterface::setAction() For the description.
     * @see SpanInterface::setTimestamp() For the description.
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
     * @see             SpanInterface::setName() For the description.
     * @see             SpanInterface::setType() For the description.
     * @see             SpanInterface::setSubtype() For the description.
     * @see             SpanInterface::setAction() For the description.
     * @see             SpanInterface::setTimestamp() For the description.
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
     * - For transactions:
     *      The name of this transaction.
     *      Generic designation of a transaction in the scope of a single service (eg: 'GET /users/:id').
     *
     * - For spans:
     *      Generic designation of a span in the scope of a transaction.
     *
     * The length of this string is limited to 1024.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/transactions/transaction.json#L47
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/spans/span.json#L136
     *
     * @param string $name
     */
    public function setName(string $name): void;

    /**
     * Type is a keyword of specific relevance in the service's domain
     * e.g.,
     *      - For transaction: 'db', 'external' for a span and 'request', 'backgroundjob' for a transaction, etc.
     *      - For span: 'db.postgresql.query', 'template.erb', etc.
     *
     * The length of this string is limited to 1024.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/transactions/transaction.json#L57
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/spans/span.json#L149
     *
     * @param string $type
     */
    public function setType(string $type): void;

    /**
     * @deprecated      Deprecated since version 1.3 - use injectDistributedTracingHeaders() instead
     * @see             injectDistributedTracingHeaders() Use it instead of this method
     *
     * Returns distributed tracing data
     */
    public function getDistributedTracingData(): ?DistributedTracingData;

    /**
     * Returns distributed tracing data for the current span/transaction
     *
     * $headerInjector is callback to inject headers with signature
     *
     *      (string $headerName, string $headerValue): void
     *
     * @param Closure $headerInjector Callback that actually injects header(s) for the underlying transport
     *
     * @phpstan-param Closure(string, string): void $headerInjector
     */
    public function injectDistributedTracingHeaders(Closure $headerInjector): void;

    /**
     * Sets the end timestamp and finalizes this object's state.
     *
     * If any mutating method (for example any `set...` method is a mutating method)
     * is called on a instance which has already then a warning is logged.
     * For example, end() is a mutating method as well.
     *
     * @param float|null $duration In milliseconds with 3 decimal points.
     */
    public function end(?float $duration = null): void;

    /**
     * Returns true if this execution segment has already ended.
     */
    public function hasEnded(): bool;

    /**
     * Creates an error based on the given Throwable instance with this execution segment as the parent.
     *
     * @param Throwable $throwable
     *
     * @return string|null ID of the reported error event or null if no event was reported
     *                      (for example, because recording is disabled)
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/errors/error.json
     */
    public function createErrorFromThrowable(Throwable $throwable): ?string;

    /**
     * Creates an error based on the given Throwable instance with this execution segment as the parent.
     *
     * @param CustomErrorData $customErrorData
     *
     * @return string|null ID of the reported error event or null if no event was reported
     *                      (for example, because recording is disabled)
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/errors/error.json
     */
    public function createCustomError(CustomErrorData $customErrorData): ?string;

    /**
     * The outcome of the transaction/span: success, failure, or unknown.
     * Outcome may be one of a limited set of permitted values
     * describing the success or failure of the transaction/span.
     * This field can be used for calculating error rates for incoming/outgoing requests.
     *
     * @link https://github.com/elastic/apm-server/blob/v7.10.0/docs/spec/transactions/transaction.json#L59
     * @link https://github.com/elastic/apm-server/blob/v7.10.0/docs/spec/spans/span.json#L54
     * @link https://github.com/elastic/apm-server/blob/v7.10.0/docs/spec/outcome.json
     *
     * @param string|null $outcome
     *
     * @return void
     */
    public function setOutcome(?string $outcome): void;

    /**
     * @see setOutcome() For the description
     */
    public function getOutcome(): ?string;

    /**
     * Returns true if this execution segment is a no-op (for example when recording is disabled).
     */
    public function isNoop(): bool;

    /**
     * Discards this execution segment.
     */
    public function discard(): void;
}
