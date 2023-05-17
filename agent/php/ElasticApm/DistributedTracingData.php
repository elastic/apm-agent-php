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
use Elastic\Apm\Impl\HttpDistributedTracing;

class DistributedTracingData
{
    /** @var string */
    public $traceId;

    /** @var string */
    public $parentId;

    /** @var bool */
    public $isSampled;

    /**
     * @deprecated Deprecated since version 1.3 - use injectHeaders() instead
     * @see             injectHeaders() Use it instead of this method
     *
     * Returns distributed tracing data for the current span/transaction
     */
    public function serializeToString(): string
    {
        return HttpDistributedTracing::buildTraceParentHeader($this);
    }

    /**
     * Gets distributed tracing data for the current span/transaction
     *
     * $headerInjector is callback to inject headers with signature
     *
     *      (string $headerName, string $headerValue): void
     *
     * @param Closure $headerInjector Callback that actually injects header(s) for the underlying transport
     *
     * @phpstan-param Closure(string, string): void $headerInjector
     */
    public function injectHeaders(Closure $headerInjector): void
    {
        $headerInjector(
            HttpDistributedTracing::TRACE_PARENT_HEADER_NAME,
            HttpDistributedTracing::buildTraceParentHeader($this)
        );
    }
}
