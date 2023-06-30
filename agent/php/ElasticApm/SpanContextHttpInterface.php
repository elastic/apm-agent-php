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

interface SpanContextHttpInterface
{
    /**
     * The raw url of the correlating http request
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/spans/span.json#L73
     *
     * @param ?string $url
     *
     * @return void
     */
    public function setUrl(?string $url): void;

    /**
     * The status code of the http request
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/spans/span.json#L77
     *
     * @param ?int $statusCode
     *
     * @return void
     */
    public function setStatusCode(?int $statusCode): void;

    /**
     * The method of the http request
     *
     * The length of a value is limited to 1024.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/spans/span.json#L81
     *
     * @param ?string $method
     *
     * @return void
     */
    public function setMethod(?string $method): void;
}
