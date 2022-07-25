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

interface SpanContextInterface extends ExecutionSegmentContextInterface
{
    /**
     * Returns an object containing contextual data for database spans
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/spans/span.json#L47
     */
    public function db(): SpanContextDbInterface;

    /**
     * Returns an object containing contextual data of the related http request
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/spans/span.json#L69
     */
    public function http(): SpanContextHttpInterface;

    /**
     * Returns an object containing contextual data about the destination for spans
     *
     * @link https://github.com/elastic/apm-server/blob/7.6/docs/spec/spans/span.json#L44
     */
    public function destination(): SpanContextDestinationInterface;

    /**
     * Returns an object for service related information can be sent per event.
     * Provided information will override the more generic information from metadata,
     * non provided fields will be set according to the metadata information.
     *
     * @link https://github.com/elastic/apm-server/blob/v7.6.0/docs/spec/spans/span.json#L134
     */
    public function service(): SpanContextServiceInterface;
}
