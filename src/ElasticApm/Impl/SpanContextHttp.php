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

use Elastic\Apm\SpanContextHttpInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 *
 * @extends         ContextDataWrapper<Span>
 */
final class SpanContextHttp extends ContextDataWrapper implements SpanContextHttpInterface
{
    /** @var SpanContextHttpData */
    private $data;

    public function __construct(Span $owner, SpanContextHttpData $data)
    {
        parent::__construct($owner);
        $this->data = $data;
    }

    /** @inheritDoc */
    public function setUrl(?string $url): void
    {
        if ($this->beforeMutating()) {
            return;
        }

        $this->data->url = $url;
    }

    /** @inheritDoc */
    public function setStatusCode(?int $statusCode): void
    {
        if ($this->beforeMutating()) {
            return;
        }

        $this->data->statusCode = $statusCode;
    }

    /** @inheritDoc */
    public function setMethod(?string $method): void
    {
        if ($this->beforeMutating()) {
            return;
        }

        $this->data->method = Tracer::limitNullableKeywordString($method);
    }
}
