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

use Elastic\Apm\SpanContextDbInterface;
use Elastic\Apm\SpanContextDestinationInterface;
use Elastic\Apm\SpanContextHttpInterface;
use Elastic\Apm\SpanContextServiceInterface;
use Elastic\Apm\SpanContextInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 *
 * @extends         ExecutionSegmentContext<Span>
 */
final class SpanContext extends ExecutionSegmentContext implements SpanContextInterface
{
    /** @var SpanContextData */
    private $data;

    /** @var SpanContextDb|null */
    private $db = null;

    /** @var SpanContextDestination|null */
    private $destination = null;

    /** @var SpanContextHttp|null */
    private $http = null;

    /** @var SpanContextService|null */
    private $service = null;

    public function __construct(Span $owner, SpanContextData $data)
    {
        parent::__construct($owner, $data);
        $this->owner = $owner;
        $this->data = $data;
    }

    /** @inheritDoc */
    public function db(): SpanContextDbInterface
    {
        if ($this->db === null) {
            $this->data->db = new SpanContextDbData();
            $this->db = new SpanContextDb($this->owner, $this->data->db);
        }

        return $this->db;
    }

    /** @inheritDoc */
    public function destination(): SpanContextDestinationInterface
    {
        if ($this->destination === null) {
            $this->data->destination = new SpanContextDestinationData();
            $this->destination = new SpanContextDestination($this->owner, $this->data->destination);
        }

        return $this->destination;
    }

    /** @inheritDoc */
    public function http(): SpanContextHttpInterface
    {
        if ($this->http === null) {
            $this->data->http = new SpanContextHttpData();
            $this->http = new SpanContextHttp($this->owner, $this->data->http);
        }

        return $this->http;
    }

    /** @inheritDoc */
    public function service(): SpanContextServiceInterface
    {
        if ($this->service === null) {
            $this->data->service = new SpanContextServiceData();
            $this->service = new SpanContextService($this->owner, $this->data->service);
        }

        return $this->service;
    }

    /**
     * @return string[]
     */
    protected static function propertiesExcludedFromLog(): array
    {
        return array_merge(parent::propertiesExcludedFromLog(), ['db', 'destination', 'http']);
    }
}
