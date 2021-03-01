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
use JsonSerializable;

/**
 * An object containing contextual data of the related http request
 *
 * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/spans/span.json#L69
 *
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
class SpanContextHttpData implements JsonSerializable, LoggableInterface
{
    use LoggableTrait;

    /**
     * @var string|null
     *
     * The raw url of the correlating http request
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/spans/span.json#L73
     */
    public $url = null;

    /**
     * @var int|null
     *
     * The status code of the http request
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/spans/span.json#L77
     */
    public $statusCode = null;

    /**
     * @var string|null
     *
     * The method of the http request
     *
     * The length of a value is limited to 1024.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/spans/span.json#L81
     */
    public $method = null;

    /**
     * @return bool
     */
    public function isEmpty(): bool
    {
        return is_null($this->url) && is_null($this->statusCode) && is_null($this->method);
    }

    public function jsonSerialize()
    {
        $result = [];

        SerializationUtil::addNameValueIfNotNull('url', $this->url, /* ref */ $result);
        SerializationUtil::addNameValueIfNotNull('status_code', $this->statusCode, /* ref */ $result);
        SerializationUtil::addNameValueIfNotNull('method', $this->method, /* ref */ $result);

        return $result;
    }
}
