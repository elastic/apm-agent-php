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
 * @extends ExecutionSegmentContext<Span>
 */
final class SpanContext extends ExecutionSegmentContext implements SpanContextInterface
{
    /**
     * @var ?SpanContextDb
     *
     * An object containing contextual data for database spans
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/spans/span.json#L47
     */
    public $db = null;

    /**
     * @var ?SpanContextDestination
     *
     * An object containing contextual data about the destination for spans
     *
     * @link https://github.com/elastic/apm-server/blob/7.6/docs/spec/spans/span.json#L44
     */
    public $destination = null;

    /**
     * @var ?SpanContextHttp
     *
     * An object containing contextual data of the related http request
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/spans/span.json#L69
     */
    public $http = null;

    /**
     * @var ?SpanContextService
     *
     * Service related information can be sent per event.
     * Provided information will override the more generic information from metadata,
     * non provided fields will be set according to the metadata information.
     *
     * @link https://github.com/elastic/apm-server/blob/v7.6.0/docs/spec/spans/span.json#L134
     */
    public $service = null;

    public function __construct(Span $owner)
    {
        parent::__construct($owner);
    }

    /** @inheritDoc */
    public function db(): SpanContextDbInterface
    {
        if ($this->db === null) {
            $this->db = new SpanContextDb($this->owner);
        }

        return $this->db;
    }

    /** @inheritDoc */
    public function destination(): SpanContextDestinationInterface
    {
        if ($this->destination === null) {
            $this->destination = new SpanContextDestination($this->owner);
        }

        return $this->destination;
    }

    /** @inheritDoc */
    public function http(): SpanContextHttpInterface
    {
        if ($this->http === null) {
            $this->http = new SpanContextHttp($this->owner);
        }

        return $this->http;
    }

    /** @inheritDoc */
    public function service(): SpanContextServiceInterface
    {
        if ($this->service === null) {
            $this->service = new SpanContextService($this->owner);
        }

        return $this->service;
    }

    /** @inheritDoc */
    public function prepareForSerialization(): bool
    {
        return parent::prepareForSerialization()
               || SerializationUtil::prepareForSerialization(/* ref */ $this->db)
               || SerializationUtil::prepareForSerialization(/* ref */ $this->destination)
               || SerializationUtil::prepareForSerialization(/* ref */ $this->http)
               || SerializationUtil::prepareForSerialization(/* ref */ $this->service);
    }

    /** @inheritDoc */
    public function jsonSerialize()
    {
        $result = SerializationUtil::preProcessResult(parent::jsonSerialize());

        SerializationUtil::addNameValueIfNotNull('db', $this->db, /* ref */ $result);
        SerializationUtil::addNameValueIfNotNull('destination', $this->destination, /* ref */ $result);
        SerializationUtil::addNameValueIfNotNull('http', $this->http, /* ref */ $result);
        SerializationUtil::addNameValueIfNotNull('service', $this->service, /* ref */ $result);

        return SerializationUtil::postProcessResult($result);
    }
}
