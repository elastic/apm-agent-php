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

/**
 * Destination service context
 *
 * @link https://github.com/elastic/apm-server/blob/7.6/docs/spec/spans/span.json#L57
 */
interface SpanContextDestinationServiceInterface
{
    /**
     * Type of the destination service (e.g. 'db', 'elasticsearch'). Should typically be the same as span.type.
     *
     * @link https://github.com/elastic/apm-server/blob/7.6/docs/spec/spans/span.json#L61
     *
     * @param string|null $type
     *
     * @return void
     */
    public function setType(?string $type): void;

    /**
     * "Identifier for the destination service (e.g. 'http://elastic.co', 'elasticsearch', 'rabbitmq')
     *
     * @link https://github.com/elastic/apm-server/blob/7.6/docs/spec/spans/span.json#L65
     *
     * @param string|null $name
     *
     * @return void
     */
    public function setName(?string $name): void;

    /**
     * Identifier for the destination service resource being operated on
     * e.g. 'http://elastic.co:80', 'elasticsearch', 'rabbitmq/queue_name'
     *
     * @link https://github.com/elastic/apm-server/blob/7.6/docs/spec/spans/span.json#L71
     *
     * @param string|null $resource
     *
     * @return void
     */
    public function setResource(?string $resource): void;
}
