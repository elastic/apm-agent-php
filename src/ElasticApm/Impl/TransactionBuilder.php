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
use Elastic\Apm\TransactionBuilderInterface;
use Elastic\Apm\TransactionInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class TransactionBuilder implements TransactionBuilderInterface
{
    /** @var Tracer */
    public $tracer;

    /** @var string */
    public $name;

    /** @var string */
    public $type;

    /** @var bool */
    public $asCurrent = false;

    /** @var ?float */
    public $timestamp = null;

    /** @var ?Closure(string $headerName): (null|string|string[]) */
    public $headersExtractor = null;

    /** @var ?string */
    public $serializedDistTracingData = null;

    public function __construct(Tracer $tracer, string $name, string $type)
    {
        $this->tracer = $tracer;
        $this->name = $name;
        $this->type = $type;
    }

    /** @inheritDoc */
    public function asCurrent(): TransactionBuilderInterface
    {
        $this->asCurrent = true;
        return $this;
    }

    /** @inheritDoc */
    public function timestamp(float $timestamp): TransactionBuilderInterface
    {
        $this->timestamp = $timestamp;
        return $this;
    }

    /** @inheritDoc */
    public function distributedTracingHeaderExtractor(Closure $headerExtractor): TransactionBuilderInterface
    {
        $this->headersExtractor = $headerExtractor;
        return $this;
    }

    /** @inheritDoc */
    public function begin(): TransactionInterface
    {
        return $this->tracer->beginTransactionWithBuilder($this);
    }

    /** @inheritDoc */
    public function capture(Closure $callback)
    {
        return $this->tracer->captureTransactionWithBuilder($this, $callback);
    }
}
