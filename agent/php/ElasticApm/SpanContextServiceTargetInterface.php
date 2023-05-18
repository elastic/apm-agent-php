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

interface SpanContextServiceTargetInterface
{
    /**
     * Immutable name of the target service for the event
     *
     * The length of this string is limited to 1024.
     *
     * @link https://github.com/elastic/apm-server/blob/v8.3.0/docs/spec/v2/span.json#L526
     *
     * @param ?string $name
     *
     * @return void
     */
    public function setName(?string $name): void;

    /**
     * Immutable type of the target service for the event
     *
     * The length of this string is limited to 1024.
     *
     * @link https://github.com/elastic/apm-server/blob/v8.3.0/docs/spec/v2/span.json#L533
     *
     * @param ?string $type
     *
     * @return void
     */
    public function setType(?string $type): void;
}
