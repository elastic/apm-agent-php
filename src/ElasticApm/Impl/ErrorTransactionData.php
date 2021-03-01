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
use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableTrait;
use Elastic\Apm\TransactionInterface;
use JsonSerializable;

/**
 * Data for correlating errors with transactions
 *
 * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/errors/error.json#L29
 *
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
class ErrorTransactionData implements JsonSerializable, LoggableInterface
{
    use LoggableTrait;

    /**
     * @var bool
     *
     * Transactions that are 'sampled' will include all available information.
     * Transactions that are not sampled will not have 'spans' or 'context'.
     * Defaults to true.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/errors/error.json#L33
     */
    public $isSampled = true;

    /**
     * @var string
     *
     * Keyword of specific relevance in the service's domain (eg: 'request', 'backgroundjob', etc)
     *
     * The length of a value is limited to 1024.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/errors/error.json#L37
     *
     * @see  TransactionInterface::setType()
     */
    public $type;

    public static function build(Transaction $transaction): ErrorTransactionData
    {
        $result = new ErrorTransactionData();

        $result->isSampled = $transaction->isSampled();
        $result->type = $transaction->getType();

        return $result;
    }

    public function jsonSerialize()
    {
        $result = [];

        // https://github.com/elastic/apm-server/blob/7.0/docs/spec/errors/error.json#L35
        // 'sampled' is optional and defaults to true.
        if (!$this->isSampled) {
            SerializationUtil::addNameValue('sampled', $this->isSampled, /* ref */ $result);
        }
        SerializationUtil::addNameValue('type', $this->type, /* ref */ $result);

        return $result;
    }
}
