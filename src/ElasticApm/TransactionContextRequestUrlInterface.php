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

namespace Elastic\Apm;

interface TransactionContextRequestUrlInterface
{
    /**
     * The full, possibly agent-assembled URL of the request
     *
     * The length of a value is limited to 1024.
     *
     * @link https://github.com/elastic/apm-server/blob/v7.0.0/docs/spec/request.json#L64
     *
     * @param ?string $full
     *
     * @return void
     */
    public function setFull(?string $full): void;

    /**
     * The hostname of the request, e.g. 'example.com'
     *
     * The length of a value is limited to 1024.
     *
     * @link https://github.com/elastic/apm-server/blob/v7.0.0/docs/spec/request.json#L69
     *
     * @param ?string $hostname
     *
     * @return void
     */
    public function setHostname(?string $hostname): void;

    /**
     * The path of the request, e.g. '/search'
     *
     * The length of a value is limited to 1024.
     *
     * @link https://github.com/elastic/apm-server/blob/v7.0.0/docs/spec/request.json#L79
     *
     * @param ?string $pathname
     *
     * @return void
     */
    public function setPathname(?string $pathname): void;

    /**
     * The port of the request, e.g. 443
     *
     * @link https://github.com/elastic/apm-server/blob/v7.0.0/docs/spec/request.json#L74
     *
     * @param ?int $port
     *
     * @return void
     */
    public function setPort(?int $port): void;

    /**
     * The protocol of the request, e.g. 'http'
     *
     * The length of a value is limited to 1024.
     *
     * @link https://github.com/elastic/apm-server/blob/v7.0.0/docs/spec/request.json#L59
     *
     * @param ?string $protocol
     *
     * @return void
     */
    public function setProtocol(?string $protocol): void;

    /**
     * The raw, unparsed URL of the HTTP request line, e.g https://example.com:443/search?q=elasticsearch.
     * This URL may be absolute or relative.
     * For more details, see https://www.w3.org/Protocols/rfc2616/rfc2616-sec5.html#sec5.1.2
     *
     * The length of a value is limited to 1024.
     *
     * @link https://github.com/elastic/apm-server/blob/v7.0.0/docs/spec/request.json#L54
     *
     * @param ?string $raw
     *
     * @return void
     */
    public function setRaw(?string $raw): void;

    /**
     * Sets the query string information of the request.
     * It is expected to have values delimited by ampersands.
     *
     * The length of a value is limited to 1024.
     *
     * @link https://github.com/elastic/apm-server/blob/v7.0.0/docs/spec/request.json#L84
     *
     * @param ?string $search
     *
     * @return void
     */
    public function setSearch(?string $search): void;
}
