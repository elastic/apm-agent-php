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

use Elastic\Apm\Impl\Util\NoopObjectTrait;
use Elastic\Apm\SpanContextInterface;
use Elastic\Apm\SpanInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class NoopSpan extends NoopExecutionSegment implements SpanInterface
{
    use NoopObjectTrait;

    /** @inheritDoc */
    public function getTransactionId(): string
    {
        return NoopExecutionSegment::ID;
    }

    /** @inheritDoc */
    public function getParentId(): string
    {
        return NoopExecutionSegment::ID;
    }

    /** @inheritDoc */
    public function setSubtype(?string $subtype): void
    {
    }

    /** @inheritDoc */
    public function setAction(?string $action): void
    {
    }

    /** @noinspection PhpUnused */
    public function setCompressible(bool $isCompressible): void
    {
    }

    /** @inheritDoc */
    public function endSpanEx(int $numberOfStackFramesToSkip, ?float $duration = null): void
    {
    }

    /** @inheritDoc */
    public function context(): SpanContextInterface
    {
        return NoopSpanContext::singletonInstance();
    }
}
