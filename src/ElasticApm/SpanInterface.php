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

interface SpanInterface extends ExecutionSegmentInterface
{
    /**
     * Hex encoded 64 random bits ID of the correlated transaction.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/spans/span.json#L14
     */
    public function getTransactionId(): string;

    /**
     * Hex encoded 64 random bits ID of the parent.
     * If this span is the root span of the correlated transaction the its parent is the correlated transaction
     * otherwise its parent is the parent span.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/spans/span.json#L24
     */
    public function getParentId(): string;

    /**
     * The specific kind of event within the sub-type represented by the span
     * e.g., 'query' for type/sub-type 'db'/'mysql', 'connect' for type/sub-type 'db'/'cassandra'
     *
     * The length of this string is limited to 1024.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/spans/span.json#L38
     *
     * @param string|null $action
     *
     * @return void
     */
    public function setAction(?string $action): void;

    /**
     * A further sub-division of the type
     * e.g., 'mysql', 'postgresql' or 'elasticsearch' for type 'db', 'http' for type 'external', etc.
     *
     * The length of this string is limited to 1024.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/spans/span.json#L33
     *
     * @param string|null $subtype
     */
    public function setSubtype(?string $subtype): void;

    /**
     * Returns context (context allows to set labels, etc.)
     */
    public function context(): SpanContextInterface;

    /**
     * Extended version of ExecutionSegmentInterface::end()
     *
     * @param int        $numberOfStackFramesToSkip Number of stack frames to skip when capturing stack trace.
     * @param float|null $duration                  In milliseconds with 3 decimal points.
     *
     * @see ExecutionSegmentInterface::end() For the description
     */
    public function endSpanEx(int $numberOfStackFramesToSkip, ?float $duration = null): void;
}
