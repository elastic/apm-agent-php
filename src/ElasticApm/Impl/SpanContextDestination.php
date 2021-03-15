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

use Elastic\Apm\SpanContextDestinationInterface;
use Elastic\Apm\SpanContextDestinationServiceInterface;
use Elastic\Apm\SpanContextHttpInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * An object containing contextual data about the destination for spans
 *
 * @link https://github.com/elastic/apm-server/blob/7.6/docs/spec/spans/span.json#L44
 *
 * @internal
 *
 * @extends         ContextDataWrapper<Span>
 */
final class SpanContextDestination extends ContextDataWrapper implements SpanContextDestinationInterface
{
    /** @var SpanContextDestinationData */
    private $data;

    public function __construct(Span $owner, SpanContextDestinationData $data)
    {
        parent::__construct($owner);
        $this->data = $data;
    }

    public function setService(string $name, string $resource, string $type): void
    {
        if ($this->beforeMutating()) {
            return;
        }

        if ($this->data->service === null) {
            $this->data->service = new SpanContextDestinationServiceData();
        }

        $this->data->service->name = Tracer::limitKeywordString($name);
        $this->data->service->resource = Tracer::limitKeywordString($resource);
        $this->data->service->type = Tracer::limitKeywordString($type);
    }

    /**
     * @return string[]
     */
    protected static function propertiesExcludedFromLog(): array
    {
        return array_merge(parent::propertiesExcludedFromLog(), ['service']);
    }
}
