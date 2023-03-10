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

namespace ElasticApmTests\Util;

use Elastic\Apm\Impl\Log\LoggableToString;
use Stringable;

final class AssertMessageBuilder implements Stringable
{
    /** @var array<string, mixed> */
    private $ctx;

    /**
     * @param array<string, mixed> $initialCtx
     */
    public function __construct(array $initialCtx = [])
    {
        $this->ctx = $initialCtx;
    }

    /**
     * @param string $key
     * @param mixed  $value
     *
     * @return void
     */
    public function add(string $key, $value): void
    {
        $this->ctx[$key] = $value;
    }

    /**
     * @param array<string, mixed> $from
     *
     * @return void
     */
    public function append(array $from): void
    {
        $this->ctx = array_merge($this->ctx, $from);
    }

    /**
     * @param array<string, mixed> $additionalCtx
     *
     * @return self
     */
    public function inherit(array $additionalCtx = []): self
    {
        return new self(array_merge($this->ctx, $additionalCtx));
    }

    public function s(): string
    {
        return LoggableToString::convert($this->ctx);
    }

    /** @inheritDoc */
    public function __toString(): string
    {
        return LoggableToString::convert($this->ctx);
    }
}
