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
use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableTrait;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class StackTraceFrame implements SerializableDataInterface, LoggableInterface
{
    use LoggableTrait;

    /**
     * @var string
     *
     * The relative filename of the code involved in the stack frame, used e.g. to do error checksumming
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/stacktrace_frame.json#L19
     */
    public $filename;

    /**
     * @var ?string
     *
     * The function involved in the stack frame
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/stacktrace_frame.json#L23
     */
    public $function = null;

    /**
     * @var int
     *
     * The line number of code part of the stack frame, used e.g. to do error checksumming
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/stacktrace_frame.json#L31
     */
    public $lineno;

    public function __construct(string $filename, int $lineno)
    {
        $this->filename = $filename;
        $this->lineno = $lineno;
    }

    /** @inheritDoc */
    public function jsonSerialize()
    {
        $result = [];

        SerializationUtil::addNameValue('filename', $this->filename, /* ref */ $result);
        SerializationUtil::addNameValueIfNotNull('function', $this->function, /* ref */ $result);
        SerializationUtil::addNameValue('lineno', $this->lineno, /* ref */ $result);

        return SerializationUtil::postProcessResult($result);
    }
}
