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

/**
 * Class to gather optional parameters to start a new transaction
 *
 * @see             ElasticApm::beginCurrentTransaction()
 * @see             ElasticApm::captureCurrentTransaction()
 */
interface TransactionBuilderInterface
{
    /**
     * New transaction will be set as the current one
     *
     * @return TransactionBuilderInterface
     */
    public function asCurrent(): self;

    /**
     * Set start time of the new transaction
     *
     * @param float $timestamp
     *
     * @return TransactionBuilderInterface
     */
    public function timestamp(float $timestamp): self;

    /**
     * @param Closure $headerExtractor
     *
     * @return TransactionBuilderInterface
     *
     * @phpstan-param Closure(string $headerName): (null|string|string[]) $headerExtractor
     */
    public function distributedTracingHeaderExtractor(Closure $headerExtractor): self;

    /**
     * Begins a new transaction.
     *
     * @return TransactionInterface New transaction
     */
    public function begin(): TransactionInterface;

    /**
     * Begins a new transaction,
     * runs the provided callback as the new transaction and automatically ends the new transaction.
     *
     * @template T
     * @param Closure(TransactionInterface): T $callback Callback to execute as the new transaction
     * @return T The return value of $callback
     */
    public function capture(Closure $callback);
}
