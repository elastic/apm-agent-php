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

use Elastic\Apm\TransactionContextInterface;
use Elastic\Apm\TransactionContextRequestInterface;
use Elastic\Apm\TransactionContextUserInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 *
 * @extends         ExecutionSegmentContext<Transaction>
 */
final class TransactionContext extends ExecutionSegmentContext implements TransactionContextInterface
{
    /** @var TransactionContextData */
    private $data;

    /** @var TransactionContextRequest|null */
    private $request = null;

    /** @var TransactionContextUser|null */
    private $user = null;

    public function __construct(Transaction $owner, TransactionContextData $data)
    {
        parent::__construct($owner, $data);
        $this->data = $data;
    }

    /** @inheritDoc */
    public function request(): TransactionContextRequestInterface
    {
        if ($this->request === null) {
            $this->data->request = new TransactionContextRequestData();
            $this->request = new TransactionContextRequest($this->owner, $this->data->request);
        }

        return $this->request;
    }

    /** @inheritDoc */
    public function user(): TransactionContextUserInterface
    {
        if ($this->user === null) {
            $this->data->user = new TransactionContextUserData();
            $this->user = new TransactionContextUser($this->owner, $this->data->user);
        }

        return $this->user;
    }
}
