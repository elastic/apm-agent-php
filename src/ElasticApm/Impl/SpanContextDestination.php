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
use Elastic\Apm\SpanContextDestinationInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * An object containing contextual data about the destination for spans
 *
 * @link https://github.com/elastic/apm-server/blob/7.6/docs/spec/spans/span.json#L44
 *
 * @internal
 *
 * @extends ContextPartWrapper<Span>
 */
final class SpanContextDestination extends ContextPartWrapper implements SpanContextDestinationInterface
{
    /**
     * @var ?SpanContextDestinationServiceData
     *
     * Destination service context
     *
     * @link https://github.com/elastic/apm-server/blob/7.6/docs/spec/spans/span.json#L57
     */
    public $service = null;

    public function __construct(Span $owner)
    {
        parent::__construct($owner);
    }

    public function setService(string $name, string $resource, string $type): void
    {
        if ($this->beforeMutating()) {
            return;
        }

        if ($this->service === null) {
            $this->service = new SpanContextDestinationServiceData();
        }

        $this->service->name = Tracer::limitKeywordString($name);
        $this->service->resource = Tracer::limitKeywordString($resource);
        $this->service->type = Tracer::limitKeywordString($type);
    }

    /** @inheritDoc */
    public function prepareForSerialization(): bool
    {
        return SerializationUtil::prepareForSerialization(/* ref */ $this->service);
    }

    /** @inheritDoc */
    public function jsonSerialize()
    {
        $result = [];

        SerializationUtil::addNameValueIfNotNull('service', $this->service, /* ref */ $result);

        return SerializationUtil::postProcessResult($result);
    }
}
