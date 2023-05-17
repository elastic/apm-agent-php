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
use Elastic\Apm\TransactionContextRequestInterface;
use Elastic\Apm\TransactionContextRequestUrlInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @link https://github.com/elastic/apm-server/blob/v7.0.0/docs/spec/request.json
 *
 * @internal
 *
 * @extends ContextPartWrapper<Transaction>
 */
final class TransactionContextRequest extends ContextPartWrapper implements TransactionContextRequestInterface
{
    public const UNKNOWN_METHOD = 'UNKNOWN HTTP METHOD';

    /** @var ?string */
    public $method = null;

    /** @var ?TransactionContextRequestUrl */
    public $url = null;

    public function __construct(Transaction $owner)
    {
        parent::__construct($owner);
    }

    /** @inheritDoc */
    public function setMethod(string $method): void
    {
        if ($this->beforeMutating()) {
            return;
        }

        $this->method = Tracer::limitNullableKeywordString($method);
    }

    /** @inheritDoc */
    public function url(): TransactionContextRequestUrlInterface
    {
        if ($this->url === null) {
            $this->url = new TransactionContextRequestUrl($this->owner);
        }

        return $this->url;
    }

    /** @inheritDoc */
    public function prepareForSerialization(): bool
    {
        if (($this->method === null) && !SerializationUtil::prepareForSerialization(/* ref */ $this->url)) {
            return false;
        }

        /**
         * @link https://github.com/elastic/apm-server/blob/v7.0.0/docs/spec/request.json#L101
         * "required": ["url", "method"]
         */
        if ($this->method === null) {
            $this->method = self::UNKNOWN_METHOD;
        }

        if ($this->url === null) {
            $this->url = new TransactionContextRequestUrl($this->owner);
        }

        return true;
    }

    /** @inheritDoc */
    public function jsonSerialize()
    {
        $result = [];

        SerializationUtil::addNameValueIfNotNull('method', $this->method, /* ref */ $result);
        SerializationUtil::addNameValueIfNotNull('url', $this->url, /* ref */ $result);

        return SerializationUtil::postProcessResult($result);
    }
}
