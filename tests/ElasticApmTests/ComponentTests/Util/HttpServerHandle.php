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

namespace ElasticApmTests\ComponentTests\Util;

use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableTrait;

class HttpServerHandle implements LoggableInterface
{
    use LoggableTrait;

    public const DEFAULT_HOST = '127.0.0.1';
    public const STATUS_CHECK_URI = '/elastic_apm_php_tests_status_check';

    /** @var string */
    private $spawnedProcessId;

    /** @var int */
    private $port;

    public function __construct(string $spawnedProcessId, int $port)
    {
        $this->spawnedProcessId = $spawnedProcessId;
        $this->port = $port;
    }

    public function getSpawnedProcessId(): string
    {
        return $this->spawnedProcessId;
    }

    public function getPort(): int
    {
        return $this->port;
    }
}
