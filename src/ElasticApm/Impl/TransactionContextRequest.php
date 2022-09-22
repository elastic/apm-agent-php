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

use Elastic\Apm\TransactionContextRequestInterface;
use Elastic\Apm\TransactionContextRequestUrlInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 *
 * @extends ContextDataWrapper<Transaction>
 */
final class TransactionContextRequest extends ContextDataWrapper implements TransactionContextRequestInterface
{
    /** @var TransactionContextRequestData */
    private $data;

    /** @var TransactionContextRequestUrl|null */
    private $url = null;

    public function __construct(Transaction $owner, TransactionContextRequestData $data)
    {
        parent::__construct($owner);
        $this->data = $data;
    }

    /** @inheritDoc */
    public function setMethod(string $method): void
    {
        if ($this->beforeMutating()) {
            return;
        }

        $this->data->method = Tracer::limitNullableKeywordString($method);
    }

    /** @inheritDoc */
    public function url(): TransactionContextRequestUrlInterface
    {
        if ($this->url === null) {
            $this->data->url = new TransactionContextRequestUrlData();
            $this->url = new TransactionContextRequestUrl($this->owner, $this->data->url);
        }

        return $this->url;
    }
}
