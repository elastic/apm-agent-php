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

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
class TransactionData extends ExecutionSegmentData
{
    /** @var string|null */
    public $parentId = null;

    /** @var int */
    public $startedSpansCount = 0;

    /** @var int */
    public $droppedSpansCount = 0;

    /** @var string|null */
    public $result = null;

    /** @var bool */
    public $isSampled = true;

    /** @var ?TransactionContextData */
    public $context = null;

    /** @inheritDoc */
    public function jsonSerialize()
    {
        $result = SerializationUtil::preProcessResult(parent::jsonSerialize());

        SerializationUtil::addNameValueIfNotNull('parent_id', $this->parentId, /* ref */ $result);

        $spanCountSubObject = ['started' => $this->startedSpansCount];
        if ($this->droppedSpansCount != 0) {
            $spanCountSubObject['dropped'] = $this->droppedSpansCount;
        }
        SerializationUtil::addNameValue('span_count', $spanCountSubObject, /* ref */ $result);

        SerializationUtil::addNameValueIfNotNull('result', $this->result, /* ref */ $result);

        // https://github.com/elastic/apm-server/blob/7.0/docs/spec/transactions/transaction.json#L72
        // 'sampled' is optional and defaults to true.
        if (!$this->isSampled) {
            SerializationUtil::addNameValue('sampled', $this->isSampled, /* ref */ $result);
        }

        SerializationUtil::addNameValueIfNotNull('context', $this->context, /* ref */ $result);

        return SerializationUtil::postProcessResult($result);
    }

    public function prepareForSerialization(): void
    {
        SerializationUtil::prepareForSerialization(/* ref */ $this->context);
    }
}
