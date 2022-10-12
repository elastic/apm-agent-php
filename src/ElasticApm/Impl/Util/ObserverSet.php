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

use Closure;
use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LogStreamInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 *
 * @template TArg
 */
final class ObserverSet implements LoggableInterface
{
    /** @var array<int, Closure(TArg): void> */
    private $callbacks = [];

    public function add(Closure $callback): void
    {
        $this->callbacks[spl_object_id($callback)] = $callback;
    }

    public function remove(Closure $callback): void
    {
        unset($this->callbacks[spl_object_id($callback)]);
    }

    /**
     * @param TArg $arg
     *
     * @return void
     */
    public function callCallbacks($arg): void
    {
        foreach ($this->callbacks as $callback) {
            $callback($arg);
        }
    }

    /** @inheritDoc */
    public function toLog(LogStreamInterface $stream): void
    {
        $stream->toLogAs(['callbacks count' => count($this->callbacks)]);
    }
}
