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

use Elastic\Apm\TransactionContextRequestUrlInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 *
 * @extends         ContextDataWrapper<Transaction>
 */
final class TransactionContextRequestUrl extends ContextDataWrapper implements TransactionContextRequestUrlInterface
{
    /** @var TransactionContextRequestUrlData */
    private $data;

    public function __construct(Transaction $owner, TransactionContextRequestUrlData $data)
    {
        parent::__construct($owner);
        $this->data = $data;
    }

    /** @inheritDoc */
    public function setFull(?string $full): void
    {
        if ($this->beforeMutating()) {
            return;
        }

        $this->data->full = Tracer::limitNullableKeywordString($full);
    }

    /** @inheritDoc */
    public function setDomain(?string $domain): void
    {
        if ($this->beforeMutating()) {
            return;
        }

        $this->data->domain = Tracer::limitNullableKeywordString($domain);
    }

    /** @inheritDoc */
    public function setOriginal(?string $original): void
    {
        if ($this->beforeMutating()) {
            return;
        }

        $this->data->original = Tracer::limitNullableKeywordString($original);
    }

    /** @inheritDoc */
    public function setPath(?string $path): void
    {
        if ($this->beforeMutating()) {
            return;
        }

        $this->data->path = Tracer::limitNullableKeywordString($path);
    }

    /** @inheritDoc */
    public function setPort(?int $port): void
    {
        if ($this->beforeMutating()) {
            return;
        }

        $this->data->port = $port;
    }

    /** @inheritDoc */
    public function setProtocol(?string $protocol): void
    {
        if ($this->beforeMutating()) {
            return;
        }

        $this->data->protocol = Tracer::limitNullableKeywordString($protocol);
    }

    /** @inheritDoc */
    public function setQuery(?string $query): void
    {
        if ($this->beforeMutating()) {
            return;
        }

        $this->data->query = Tracer::limitNullableKeywordString($query);
    }
}
