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

final class EventCounts implements LoggableInterface
{
    use LoggableTrait;

    /** @var int */
    public $transactionCount = 0;

    /** @var int */
    public $spanCount = 0;

    /** @var int */
    public $errorCount = 0;

    /** @var int */
    public $metricCount = 0;

    public function transactions(int $count): self
    {
        $this->transactionCount = $count;
        return $this;
    }

    public function spans(int $count): self
    {
        $this->spanCount = $count;
        return $this;
    }

    public function errors(int $count): self
    {
        $this->errorCount = $count;
        return $this;
    }

    public function metrics(int $count): self
    {
        $this->metricCount = $count;
        return $this;
    }
}
