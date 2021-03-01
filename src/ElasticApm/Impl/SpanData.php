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
class SpanData extends ExecutionSegmentData
{
    /** @var string */
    public $parentId;

    /** @var string */
    public $transactionId;

    /** @var string|null */
    public $action = null;

    /** @var string|null */
    public $subtype = null;

    /** @var StacktraceFrame[]|null */
    public $stacktrace = null;

    /** @var SpanContextData|null */
    public $context = null;

    public function jsonSerialize()
    {
        $result = parent::jsonSerialize();

        SerializationUtil::addNameValue('parent_id', $this->parentId, /* ref */ $result);
        SerializationUtil::addNameValue('transaction_id', $this->transactionId, /* ref */ $result);
        SerializationUtil::addNameValueIfNotNull('action', $this->action, /* ref */ $result);
        SerializationUtil::addNameValueIfNotNull('subtype', $this->subtype, /* ref */ $result);
        SerializationUtil::addNameValueIfNotNull('stacktrace', $this->stacktrace, /* ref */ $result);

        if (!is_null($this->context)) {
            SerializationUtil::addNameValueIfNotEmpty('context', $this->context->jsonSerialize(), /* ref */ $result);
        }

        return $result;
    }
}
