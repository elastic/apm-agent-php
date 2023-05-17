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

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 *
 * @extends ContextPartWrapper<Span>
 */
final class SpanContextDb extends ContextPartWrapper implements SpanContextDbInterface
{
    /**
     * @var ?string
     *
     * A database statement (e.g. query) for the given database type
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/spans/span.json#L55
     */
    public $statement = null;

    public function __construct(Span $owner)
    {
        parent::__construct($owner);
    }

    /** @inheritDoc */
    public function setStatement(?string $statement): void
    {
        if ($this->beforeMutating()) {
            return;
        }

        $this->statement = $statement;
    }

    /** @inheritDoc */
    public function prepareForSerialization(): bool
    {
        return ($this->statement !== null);
    }

    /** @inheritDoc */
    public function jsonSerialize()
    {
        $result = [];

        SerializationUtil::addNameValueIfNotNull('statement', $this->statement, /* ref */ $result);

        return SerializationUtil::postProcessResult($result);
    }
}
