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
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * Destination service context
 *
 * @link https://github.com/elastic/apm-server/blob/7.6/docs/spec/spans/span.json#L57
 *
 * @internal
 */
class SpanContextDestinationServiceData implements ContextDataInterface, LoggableInterface
{
    use LoggableTrait;

    /**
     * @var string
     *
     * "Identifier for the destination service (e.g. 'http://elastic.co', 'elasticsearch', 'rabbitmq')
     *
     * @link https://github.com/elastic/apm-server/blob/v7.11.0/docs/spec/v2/span.json#L113
     */
    public $name;

    /**
     * @var string
     *
     * Identifier for the destination service resource being operated on
     * e.g. 'http://elastic.co:80', 'elasticsearch', 'rabbitmq/queue_name'
     *
     * @link https://github.com/elastic/apm-server/blob/v7.11.0/docs/spec/v2/span.json#L118
     */
    public $resource;

    /**
     * @var string
     *
     * Type of the destination service (e.g. 'db', 'elasticsearch'). Should typically be the same as span.type.
     *
     * @link https://github.com/elastic/apm-server/blob/v7.11.0/docs/spec/v2/span.json#L123
     */
    public $type;

    public function isEmpty(): bool
    {
        return false;
    }

    /** @inheritDoc */
    public function jsonSerialize(): array
    {
        $result = [];

        SerializationUtil::addNameValue('name', $this->name, /* ref */ $result);
        SerializationUtil::addNameValue('resource', $this->resource, /* ref */ $result);
        SerializationUtil::addNameValue('type', $this->type, /* ref */ $result);

        return $result;
    }
}
