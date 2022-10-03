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

use Elastic\Apm\Impl\BackendComm\SerializationUtil;
use Elastic\Apm\TransactionContextRequestUrlInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 *
 * @extends ContextPartWrapper<Transaction>
 */
final class TransactionContextRequestUrl extends ContextPartWrapper implements TransactionContextRequestUrlInterface
{
    /** @var ?string */
    public $domain = null;

    /** @var ?string */
    public $full = null;

    /** @var ?string */
    public $original = null;

    /** @var ?string */
    public $path = null;

    /** @var ?int */
    public $port = null;

    /** @var ?string */
    public $protocol = null;

    /** @var ?string */
    public $query = null;

    public function __construct(Transaction $owner)
    {
        parent::__construct($owner);
    }

    /** @inheritDoc */
    public function setFull(?string $full): void
    {
        if ($this->beforeMutating()) {
            return;
        }

        $this->full = Tracer::limitNullableKeywordString($full);
    }

    /** @inheritDoc */
    public function setDomain(?string $domain): void
    {
        if ($this->beforeMutating()) {
            return;
        }

        $this->domain = Tracer::limitNullableKeywordString($domain);
    }

    /** @inheritDoc */
    public function setOriginal(?string $original): void
    {
        if ($this->beforeMutating()) {
            return;
        }

        $this->original = Tracer::limitNullableKeywordString($original);
    }

    /** @inheritDoc */
    public function setPath(?string $path): void
    {
        if ($this->beforeMutating()) {
            return;
        }

        $this->path = Tracer::limitNullableKeywordString($path);
    }

    /** @inheritDoc */
    public function setPort(?int $port): void
    {
        if ($this->beforeMutating()) {
            return;
        }

        $this->port = $port;
    }

    /** @inheritDoc */
    public function setProtocol(?string $protocol): void
    {
        if ($this->beforeMutating()) {
            return;
        }

        $this->protocol = Tracer::limitNullableKeywordString($protocol);
    }

    /** @inheritDoc */
    public function setQuery(?string $query): void
    {
        if ($this->beforeMutating()) {
            return;
        }

        $this->query = Tracer::limitNullableKeywordString($query);
    }

    /** @inheritDoc */
    public function prepareForSerialization(): bool
    {
        return ($this->domain !== null)
               || ($this->full !== null)
               || ($this->original !== null)
               || ($this->path !== null)
               || ($this->port !== null)
               || ($this->protocol !== null)
               || ($this->query !== null);
    }

    /** @inheritDoc */
    public function jsonSerialize()
    {
        $result = [];

        SerializationUtil::addNameValueIfNotNull('hostname', $this->domain, /* ref */ $result);
        SerializationUtil::addNameValueIfNotNull('full', $this->full, /* ref */ $result);
        SerializationUtil::addNameValueIfNotNull('raw', $this->original, /* ref */ $result);
        SerializationUtil::addNameValueIfNotNull('pathname', $this->path, /* ref */ $result);
        SerializationUtil::addNameValueIfNotNull('port', $this->port, /* ref */ $result);
        SerializationUtil::addNameValueIfNotNull('protocol', $this->protocol, /* ref */ $result);
        SerializationUtil::addNameValueIfNotNull('search', $this->query, /* ref */ $result);

        return SerializationUtil::postProcessResult($result);
    }
}
