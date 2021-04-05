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

/**
 * A complete Url, with scheme, host and path
 *
 * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/request.json#L50
 *
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class TransactionContextRequestUrlData implements OptionalSerializableDataInterface, LoggableInterface
{
    use LoggableTrait;

    /**
     * @var ?string
     *
     * The full, possibly agent-assembled URL of the request
     *
     * The length of a value is limited to 1024.
     *
     * @link https://github.com/elastic/apm-server/blob/v7.0.0/docs/spec/request.json#L64
     */
    public $full = null;

    /**
     * @var ?string
     *
     * The hostname of the request, e.g. 'example.com'
     *
     * The length of a value is limited to 1024.
     *
     * @link https://github.com/elastic/apm-server/blob/v7.0.0/docs/spec/request.json#L69
     */
    public $hostname = null;

    /**
     * @var ?string
     *
     * The path of the request, e.g. '/search'
     *
     * The length of a value is limited to 1024.
     *
     * @link https://github.com/elastic/apm-server/blob/v7.0.0/docs/spec/request.json#L79
     */
    public $pathname = null;

    /**
     * @var ?int
     *
     * The port of the request, e.g. 443
     *
     * @link https://github.com/elastic/apm-server/blob/v7.0.0/docs/spec/request.json#L74
     */
    public $port = null;

    /**
     * @var ?string
     *
     * The protocol of the request, e.g. 'http'
     *
     * The length of a value is limited to 1024.
     *
     * @link https://github.com/elastic/apm-server/blob/v7.0.0/docs/spec/request.json#L59
     */
    public $protocol = null;

    /**
     * @var ?string
     *
     * The raw, unparsed URL of the HTTP request line, e.g https://example.com:443/search?q=elasticsearch.
     * This URL may be absolute or relative.
     * For more details, see https://www.w3.org/Protocols/rfc2616/rfc2616-sec5.html#sec5.1.2
     *
     * The length of a value is limited to 1024.
     *
     * @link https://github.com/elastic/apm-server/blob/v7.0.0/docs/spec/request.json#L54
     */
    public $raw = null;

    /**
     * @var ?string
     *
     * Contains the query string information of the request.
     * It is expected to have values delimited by ampersands.
     *
     * The length of a value is limited to 1024.
     *
     * @link https://github.com/elastic/apm-server/blob/v7.0.0/docs/spec/request.json#L84
     */
    public $search = null;

    /** @inheritDoc */
    public function prepareForSerialization(): bool
    {
        return ($this->full !== null)
               || ($this->hostname !== null)
               || ($this->pathname !== null)
               || ($this->port !== null)
               || ($this->protocol !== null)
               || ($this->raw !== null)
               || ($this->search !== null);
    }

    /** @inheritDoc */
    public function jsonSerialize()
    {
        $result = [];

        SerializationUtil::addNameValueIfNotNull('full', $this->full, /* ref */ $result);
        SerializationUtil::addNameValueIfNotNull('hostname', $this->hostname, /* ref */ $result);
        SerializationUtil::addNameValueIfNotNull('pathname', $this->pathname, /* ref */ $result);
        SerializationUtil::addNameValueIfNotNull('port', $this->port, /* ref */ $result);
        SerializationUtil::addNameValueIfNotNull('protocol', $this->protocol, /* ref */ $result);
        SerializationUtil::addNameValueIfNotNull('raw', $this->raw, /* ref */ $result);
        SerializationUtil::addNameValueIfNotNull('search', $this->search, /* ref */ $result);

        return SerializationUtil::postProcessResult($result);
    }
}
