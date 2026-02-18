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

namespace Elastic\Apm\Impl\Util;

use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableTrait;
use Stringable;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class UrlParts implements LoggableInterface, Stringable
{
    use LoggableTrait;

    /** @var ?string */
    public $scheme = null;

    /** @var ?string */
    public $host = null;

    /** @var ?int */
    public $port = null;

    /** @var ?string */
    public $path = null;

    /** @var ?string */
    public $query = null;

    public function scheme(?string $scheme): self
    {
        $this->scheme = $scheme;
        return $this;
    }

    public function host(?string $host): self
    {
        $this->host = $host;
        return $this;
    }

    public function port(?int $port): self
    {
        $this->port = $port;
        return $this;
    }

    public function path(?string $path): self
    {
        $this->path = $path;
        return $this;
    }

    public function query(?string $query): self
    {
        $this->query = $query;
        return $this;
    }

    public function __toString(): string
    {
        return '{'
               . 'path: ' . $this->path
               . ', '
               . 'query: ' . $this->query
               . '}';
    }
}
