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
 * Any arbitrary contextual information regarding the event, captured by the agent, optionally provided by the user
 *
 * @link https://github.com/elastic/apm-server/blob/v7.0.0/docs/spec/context.json
 *
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class TransactionContextData extends ExecutionSegmentContextData
{
    /**
     * @var ?TransactionContextRequestData
     *
     * If a log record was generated as a result of a http request,
     * the http interface can be used to collect this information
     *
     * @link https://github.com/elastic/apm-server/blob/v7.0.0/docs/spec/context.json#L43
     * @link https://github.com/elastic/apm-server/blob/v7.0.0/docs/spec/request.json
     */
    public $request = null;

    /** @inheritDoc */
    public function prepareForSerialization(): bool
    {
        return parent::prepareForSerialization()
               || SerializationUtil::prepareForSerialization(/* ref */ $this->request);
    }

    /** @inheritDoc */
    public function jsonSerialize()
    {
        $result = SerializationUtil::preProcessResult(parent::jsonSerialize());

        SerializationUtil::addNameValueIfNotNull('request', $this->request, /* ref */ $result);

        return SerializationUtil::postProcessResult($result);
    }
}
