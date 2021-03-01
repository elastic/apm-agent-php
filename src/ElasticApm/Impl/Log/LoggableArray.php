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

namespace Elastic\Apm\Impl\Log;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class LoggableArray implements LoggableInterface
{
    private const COUNT_KEY = 'count';
    private const ARRAY_TYPE = 'array';

    /** @var array<mixed, mixed> */
    private $wrappedArray;

    /**
     * @param array<mixed, mixed> $wrappedArray
     */
    public function __construct(array $wrappedArray)
    {
        $this->wrappedArray = $wrappedArray;
    }

    public function toLog(LogStreamInterface $stream): void
    {
        if ($stream->isLastLevel()) {
            $stream->toLogAs(
                [LogConsts::TYPE_KEY => self::ARRAY_TYPE, self::COUNT_KEY => count($this->wrappedArray)]
            );
            return;
        }

        $stream->toLogAs(
            [LogConsts::TYPE_KEY => self::ARRAY_TYPE, self::COUNT_KEY => count($this->wrappedArray)]
        );
    }
}
