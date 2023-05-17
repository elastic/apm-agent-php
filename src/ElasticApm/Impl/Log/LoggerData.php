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
final class LoggerData implements LoggableInterface
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
    public $context;

    /** @var Backend */
    public $backend;

    /**
     * @param string               $category
     * @param string               $namespace
     * @param class-string         $fqClassName
     * @param string               $srcCodeFile
     * @param array<string, mixed> $context
     * @param Backend              $backend
     * @param ?LoggerData          $inheritedData
     */
    private function __construct(
        string $category,
        string $namespace,
        string $fqClassName,
        string $srcCodeFile,
        array $context,
        Backend $backend,
        ?LoggerData $inheritedData
    ) {
        $this->category = $category;
        $this->namespace = $namespace;
        $this->fqClassName = $fqClassName;
        $this->srcCodeFile = $srcCodeFile;
        $this->context = $context;
        $this->backend = $backend;
        $this->inheritedData = $inheritedData;
    }

    /**
     * @param string               $category
     * @param string               $namespace
     * @param class-string         $fqClassName
     * @param string               $srcCodeFile
     * @param array<string, mixed> $context
     * @param Backend              $backend
     *
     * @return self
     */
    public static function makeRoot(
        string $category,
        string $namespace,
        string $fqClassName,
        string $srcCodeFile,
        array $context,
        Backend $backend
    ): self {
        return new self(
            $category,
            $namespace,
            $fqClassName,
            $srcCodeFile,
            $context,
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
            [] /* <- context */,
            $this->backend,
            $this
        );
    }

    public function toLog(LogStreamInterface $stream): void
    {
        $stream->toLogAs(
            [
                'category'       => $this->category,
                'namespace'      => $this->namespace,
                'fqClassName'    => $this->fqClassName,
                'srcCodeFile'    => $this->srcCodeFile,
                'inheritedData'  => $this->inheritedData,
                'count(context)' => count($this->context),
                'backend'        => $this->backend,
            ]
        );
    }
}
