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

use Throwable;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class EnabledLoggerProxy
{
    /** @var int */
    private $statementLevel;

    /** @var int */
    private $srcCodeLine;

    /** @var string */
    private $srcCodeFunc;

    /** @var LoggerData */
    private $loggerData;

    /** @var ?bool */
    private $includeStackTrace = null;

    public function __construct(
        int $statementLevel,
        int $srcCodeLine,
        string $srcCodeFunc,
        LoggerData $loggerData
    ) {
        $this->statementLevel = $statementLevel;
        $this->srcCodeLine = $srcCodeLine;
        $this->srcCodeFunc = $srcCodeFunc;
        $this->loggerData = $loggerData;
    }

    public function includeStackTrace(bool $shouldIncludeStackTrace = true): self
    {
        $this->includeStackTrace = $shouldIncludeStackTrace;
        return $this;
    }

    /**
     * @param string               $message
     * @param array<string, mixed> $statementCtx
     *
     * @return bool
     */
    public function log(string $message, array $statementCtx = []): bool
    {
        $this->loggerData->backend->log(
            $this->statementLevel,
            $message,
            $statementCtx,
            $this->srcCodeLine,
            $this->srcCodeFunc,
            $this->loggerData,
            $this->includeStackTrace,
            /* numberOfStackFramesToSkip */ 1
        );
        // return dummy bool to suppress PHPStan's "Right side of && is always false"
        return true;
    }

    /**
     * @param Throwable            $throwable
     * @param string               $message
     * @param array<string, mixed> $statementCtx
     *
     * @return bool
     */
    public function logThrowable(Throwable $throwable, string $message, array $statementCtx = []): bool
    {
        $this->loggerData->backend->log(
            $this->statementLevel,
            $message,
            $statementCtx + ['throwable' => $throwable],
            $this->srcCodeLine,
            $this->srcCodeFunc,
            $this->loggerData,
            $this->includeStackTrace,
            /* numberOfStackFramesToSkip */ 1
        );
        // return dummy bool to suppress PHPStan's "Right side of && is always false"
        return true;
    }
}
