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

namespace ElasticApmTests\UnitTests\Util;

class MockLogSinkStatement
{
    /** @var int */
    public $statementLevel;

    /** @var string */
    public $message;

    /** @var array<string, mixed>[] */
    public $contextsStack;

    /** @var string */
    public $category;

    /** @var string */
    public $srcCodeFile;

    /** @var int */
    public $srcCodeLine;

    /** @var string */
    public $srcCodeFunc;

    /** @var ?bool */
    public $includeStacktrace;

    /** @var int */
    public $numberOfStackFramesToSkip;

    /**
     * @param int                    $statementLevel
     * @param string                 $message
     * @param array<string, mixed>[] $contextsStack
     * @param string                 $category
     * @param string                 $srcCodeFile
     * @param int                    $srcCodeLine
     * @param string                 $srcCodeFunc
     * @param bool|null              $includeStacktrace
     * @param int                    $numberOfStackFramesToSkip
     */
    public function __construct(
        int $statementLevel,
        string $message,
        array $contextsStack,
        string $category,
        string $srcCodeFile,
        int $srcCodeLine,
        string $srcCodeFunc,
        ?bool $includeStacktrace,
        int $numberOfStackFramesToSkip
    ) {
        $this->statementLevel = $statementLevel;
        $this->message = $message;
        $this->contextsStack = $contextsStack;
        $this->category = $category;
        $this->srcCodeFile = $srcCodeFile;
        $this->srcCodeLine = $srcCodeLine;
        $this->srcCodeFunc = $srcCodeFunc;
        $this->includeStacktrace = $includeStacktrace;
        $this->numberOfStackFramesToSkip = $numberOfStackFramesToSkip;
    }
}
