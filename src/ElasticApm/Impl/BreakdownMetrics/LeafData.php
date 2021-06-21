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

namespace Elastic\Apm\Impl\BreakdownMetrics;

use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableTrait;

/**
 * An error or a logged error message captured by an agent occurring in a monitored service
 *
 * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/errors/error.json
 *
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
class LeafData implements LoggableInterface
{
    use LoggableTrait;

    /** @var int */
    public $count;

    /** @var float */
    public $sumMicroseconds;

    public function add(float $selfTimeInMicroseconds): void
    {
        ++$this->count;
        $this->sumMicroseconds += $selfTimeInMicroseconds;
    }
}
