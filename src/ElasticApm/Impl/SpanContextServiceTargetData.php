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
 * Target holds information about the outgoing service in case of an outgoing event
 *
 * @link https://github.com/elastic/apm-server/blob/v8.3.0/docs/spec/v2/span.json#L519
 *
 * @internal
 */
final class SpanContextServiceTargetData implements OptionalSerializableDataInterface, LoggableInterface
{
    use LoggableTrait;

    /**
     * @var ?string
     *
     * Immutable name of the target service for the event
     *
     * The length of this string is limited to 1024.
     *
     * @link https://github.com/elastic/apm-server/blob/v8.3.0/docs/spec/v2/span.json#L526
     */
    public $name;

    /**
     * @var ?string
     *
     * Immutable type of the target service for the event
     *
     * The length of this string is limited to 1024.
     *
     * @link https://github.com/elastic/apm-server/blob/v8.3.0/docs/spec/v2/span.json#L533
     */
    public $type;

    /** @inheritDoc */
    public function prepareForSerialization(): bool
    {
        return ($this->name !== null) || ($this->type !== null);
    }

    /** @inheritDoc */
    public function jsonSerialize()
    {
        $result = [];

        SerializationUtil::addNameValueIfNotNull('name', $this->name, /* ref */ $result);
        SerializationUtil::addNameValueIfNotNull('type', $this->type, /* ref */ $result);

        return SerializationUtil::postProcessResult($result);
    }
}
