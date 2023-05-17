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
final class LoggerData
{
    /** @var string */
    public $category;

    /** @var string */
    public $namespace;

    /** @var class-string */
    public $fqClassName;

    /** @var string */
    public $srcCodeFile;

    /** @var ?LoggerData */
    public $inheritedData;

    /** @var array<string, mixed> */
    public $context = [];

    /** @var Backend */
    public $backend;

    /**
     * @param string       $category
     * @param string       $namespace
     * @param class-string $fqClassName
     * @param string       $srcCodeFile
     * @param Backend      $backend
     * @param ?LoggerData  $inheritedData
     */
    private function __construct(
        string $category,
        string $namespace,
        string $fqClassName,
        string $srcCodeFile,
        Backend $backend,
        ?LoggerData $inheritedData
    ) {
        $this->category = $category;
        $this->namespace = $namespace;
        $this->fqClassName = $fqClassName;
        $this->srcCodeFile = $srcCodeFile;
        $this->backend = $backend;
        $this->inheritedData = $inheritedData;
    }

    /**
     * @param string       $category
     * @param string       $namespace
     * @param class-string $fqClassName
     * @param string       $srcCodeFile
     * @param Backend      $backend
     *
     * @return static
     */
    public static function makeRoot(
        string $category,
        string $namespace,
        string $fqClassName,
        string $srcCodeFile,
        Backend $backend
    ): self {
        return new self(
            $category,
            $namespace,
            $fqClassName,
            $srcCodeFile,
            $backend,
            /* inheritedData */ null
        );
    }

    public function inherit(): self
    {
        return new self(
            $this->category,
            $this->namespace,
            $this->fqClassName,
            $this->srcCodeFile,
            $this->backend,
            $this
        );
    }
}
