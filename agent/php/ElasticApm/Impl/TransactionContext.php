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
use Elastic\Apm\Impl\Util\ArrayUtil;
use Elastic\Apm\TransactionContextInterface;
use Elastic\Apm\TransactionContextRequestInterface;
use Elastic\Apm\TransactionContextUserInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 *
 * @extends ExecutionSegmentContext<Transaction>
 */
final class TransactionContext extends ExecutionSegmentContext implements TransactionContextInterface
{
    /** @var ?array<string, string|bool|int|float|null> */
    public $custom = null;

    /** @var ?TransactionContextRequest */
    public $request = null;

    /** @var ?TransactionContextUser */
    public $user = null;

    public function __construct(Transaction $owner)
    {
        parent::__construct($owner);
    }

    /** @inheritDoc */
    public function request(): TransactionContextRequestInterface
    {
        if ($this->request === null) {
            $this->request = new TransactionContextRequest($this->owner);
        }

        return $this->request;
    }

    /**
     * @param string                     $key
     * @param string|bool|int|float|null $value
     *
     * @return void
     */
    public function setCustom(string $key, $value): void
    {
        $this->setInKeyValueMap($key, $value, /* enforceKeywordString */ false, /* ref */ $this->custom, 'custom');
    }

    /** @inheritDoc */
    public function user(): TransactionContextUserInterface
    {
        if ($this->user === null) {
            $this->user = new TransactionContextUser($this->owner);
        }

        return $this->user;
    }

    /** @inheritDoc */
    public function prepareForSerialization(): bool
    {
        return parent::prepareForSerialization()
               || ($this->custom !== null && !ArrayUtil::isEmpty($this->custom))
               || SerializationUtil::prepareForSerialization(/* ref */ $this->request)
               || SerializationUtil::prepareForSerialization(/* ref */ $this->user);
    }

    /** @inheritDoc */
    public function jsonSerialize()
    {
        $result = SerializationUtil::preProcessResult(parent::jsonSerialize());

        if ($this->custom !== null) {
            SerializationUtil::addNameValueIfNotEmpty('custom', $this->custom, /* ref */ $result);
        }
        SerializationUtil::addNameValueIfNotNull('request', $this->request, /* ref */ $result);
        SerializationUtil::addNameValueIfNotNull('user', $this->user, /* ref */ $result);

        return SerializationUtil::postProcessResult($result);
    }
}
