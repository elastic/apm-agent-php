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

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class ClassicFormatStackTraceFrame
{
    /** @var ?string */
    public $file = null;

    /** @var ?int */
    public $line = null;

    /** @var ?string */
    public $class = null;

    /** @var ?string */
    public $function = null;

    /** @var ?bool */
    public $isStaticMethod = null;

    /** @var ?object */
    public $thisObj = null;

    /** @var null|mixed[] */
    public $args = null;

    /**
     * @param ?string      $file
     * @param ?int         $line
     * @param ?string      $class
     * @param ?bool        $isStaticMethod
     * @param ?string      $function
     * @param ?object      $thisObj
     * @param null|mixed[] $args
     */
    public function __construct(?string $file = null, ?int $line = null, ?string $class = null, ?bool $isStaticMethod = null, ?string $function = null, ?object $thisObj = null, ?array $args = null)
    {
        $this->file = $file;
        $this->line = $line;
        $this->class = $class;
        $this->function = $function;
        $this->isStaticMethod = $isStaticMethod;
        $this->thisObj = $thisObj;
        $this->args = $args;
    }
}
