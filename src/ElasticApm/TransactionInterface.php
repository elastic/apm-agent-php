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

interface TransactionInterface extends ExecutionSegmentInterface
{
    /**
     * Transactions that are 'sampled' will include all available information
     * Transactions that are not sampled will not have 'spans' or 'context'.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/transactions/transaction.json#L72
     */
    public function isSampled(): bool;

    /**
     * Hex encoded 64 random bits ID of the parent transaction or span.
     * Only a root transaction of a trace does not have a parent ID, otherwise it needs to be set.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/transactions/transaction.json#L19
     */
    public function getParentId(): ?string;

    /**
     * Begins a new span with the current execution segment
     * as the new span's parent and sets as the new span as the current span for this transaction.
     * The current execution segment is the current span if there is one or this transaction itself otherwise.
     *
     * @param string      $name      New span's name.
     * @param string      $type      New span's type
     * @param string|null $subtype   New span's subtype
     * @param string|null $action    New span's action
     * @param float|null  $timestamp Start time of the new span
     *
     * @see SpanInterface::setName() For the description.
     * @see SpanInterface::setType() For the description.
     * @see SpanInterface::setSubtype() For the description.
     * @see SpanInterface::setAction() For the description.
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
     * The current execution segment is the current span if there is one or this transaction itself otherwise.
     *
     * @template T
     *
     * @param string      $name      New span's name
     * @param string      $type      New span's type
     * @param Closure(SpanInterface $newSpan): T $callback
     * @param string|null $subtype   New span's subtype
     * @param string|null $action    New span's action
     * @param float|null  $timestamp Start time of the new span
     *
     * @return  T The return value of $callback
     *
     * @see             SpanInterface::setName() For the description.
     * @see             SpanInterface::setType() For the description.
     * @see             SpanInterface::setSubtype() For the description.
     * @see             SpanInterface::setAction() For the description.
     * @see             SpanInterface::getTimestamp() For the description.
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
     * Returns context (context allows to set labels, etc.)
     */
    public function context(): TransactionContextInterface;

    /**
     * The result of the transaction.
     * For HTTP-related transactions, this should be the status code formatted like 'HTTP 2xx'.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/transactions/transaction.json#L52
     *
     * @param string|null $result
     *
     * @return void
     */
    public function setResult(?string $result): void;

    /**
     * @see setResult() For the description
     */
    public function getResult(): ?string;

    /**
     * If the transaction does not have a parent ID yet,
     * calling this method generates a new ID,
     * sets it as the parent ID of this transaction, and returns it as a string.
     *
     * @return string
     */
    public function ensureParentId(): string;
}
