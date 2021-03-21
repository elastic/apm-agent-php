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
class SpanContextData extends ExecutionSegmentContextData
{
    /**
     * @var SpanContextDbData|null
     *
     * An object containing contextual data for database spans
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/spans/span.json#L47
     */
    public $db = null;

    /**
     * @var SpanContextDestinationData|null
     *
     * An object containing contextual data about the destination for spans
     *
     * @link https://github.com/elastic/apm-server/blob/7.6/docs/spec/spans/span.json#L44
     */
    public $destination = null;

    /**
     * @var SpanContextHttpData|null
     *
     * An object containing contextual data of the related http request
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/spans/span.json#L69
     */
    public $http = null;

    /** @inheritDoc */
    public function isEmpty(): bool
    {
        return parent::isEmpty()
               && SerializationUtil::isNullOrEmpty($this->db)
               && SerializationUtil::isNullOrEmpty($this->destination)
               && SerializationUtil::isNullOrEmpty($this->http);
    }

    /** @inheritDoc */
    public function jsonSerialize(): array
    {
        $result = parent::jsonSerialize();

        SerializationUtil::addNameValueIfNotNullOrEmpty('db', $this->db, /* ref */ $result);
        SerializationUtil::addNameValueIfNotNullOrEmpty('destination', $this->destination, /* ref */ $result);
        SerializationUtil::addNameValueIfNotNullOrEmpty('http', $this->http, /* ref */ $result);

        return $result;
    }
}
