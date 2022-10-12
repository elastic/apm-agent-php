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

use Psr\Http\Message\ResponseInterface;
use React\EventLoop\TimerInterface;

final class MockApmServerPendingDataRequest
{
    /** @var int */
    public $fromIndex;

    /** @var callable(ResponseInterface): void */
    public $resolveCallback;

    /** @var TimerInterface */
    public $timer;

    /**
     * @param int                               $fromIndex
     * @param callable(ResponseInterface): void $resolveCallback
     * @param TimerInterface                    $timer
     */
    public function __construct(int $fromIndex, callable $resolveCallback, TimerInterface $timer)
    {
        $this->fromIndex = $fromIndex;
        $this->resolveCallback = $resolveCallback;
        $this->timer = $timer;
    }
}
