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

namespace Elastic\Apm\Impl;

use Closure;
use Elastic\Apm\Impl\Util\NoopObjectTrait;
use Elastic\Apm\SpanInterface;
use Elastic\Apm\TransactionContextInterface;
use Elastic\Apm\TransactionInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class NoopTransaction extends NoopExecutionSegment implements TransactionInterface
{
    use NoopObjectTrait;

    /** @inheritDoc */
    public function getParentId(): ?string
    {
        return null;
    }

    /** @inheritDoc */
    public function beginCurrentSpan(
        string $name,
        string $type,
        ?string $subtype = null,
        ?string $action = null,
        ?float $timestamp = null
    ): SpanInterface {
        return NoopSpan::singletonInstance();
    }

    /** @inheritDoc */
    public function captureCurrentSpan(
        string $name,
        string $type,
        Closure $callback,
        ?string $subtype = null,
        ?string $action = null,
        ?float $timestamp = null
    ) {
        return $callback(NoopSpan::singletonInstance());
    }

    /** @inheritDoc */
    public function getCurrentSpan(): SpanInterface
    {
        return NoopSpan::singletonInstance();
    }

    /** @inheritDoc */
    public function setResult(?string $result): void
    {
    }

    /** @inheritDoc */
    public function getResult(): ?string
    {
        return null;
    }

    /** @inheritDoc */
    public function isSampled(): bool
    {
        return false;
    }

    /** @inheritDoc */
    public function context(): TransactionContextInterface
    {
        return NoopTransactionContext::singletonInstance();
    }
}
