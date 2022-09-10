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
final class Logger
{
    /** @var LoggerData */
    private $data;

    /** @var int */
    private $maxEnabledLevel;

    private function __construct(LoggerData $data, int $maxEnabledLevel)
    {
        $this->data = $data;
        $this->maxEnabledLevel = $maxEnabledLevel;
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
            LoggerData::makeRoot($category, $namespace, $fqClassName, $srcCodeFile, $backend),
            $backend->maxEnabledLevel()
        );
    }

    public function inherit(): self
    {
        return new self(LoggerData::inherit($this->data), $this->maxEnabledLevel);
    }

    /**
     * @param string $key
     * @param mixed  $value
     *
     * @return Logger
     */
    public function addContext(string $key, $value): Logger
    {
        $this->data->context[$key] = $value;
        return $this;
    }

    /**
     * @param array<string, mixed> $keyValuePairs
     *
     * @return Logger
     */
    public function addAllContext(array $keyValuePairs): Logger
    {
        foreach ($keyValuePairs as $key => $value) {
            $this->addContext($key, $value);
        }
        return $this;
    }

    public function ifCriticalLevelEnabled(int $srcCodeLine, string $srcCodeFunc): ?EnabledLoggerProxy
    {
        return $this->ifLevelEnabled(Level::CRITICAL, $srcCodeLine, $srcCodeFunc);
    }

    public function ifErrorLevelEnabled(int $srcCodeLine, string $srcCodeFunc): ?EnabledLoggerProxy
    {
        return $this->ifLevelEnabled(Level::ERROR, $srcCodeLine, $srcCodeFunc);
    }

    public function ifWarningLevelEnabled(int $srcCodeLine, string $srcCodeFunc): ?EnabledLoggerProxy
    {
        return $this->ifLevelEnabled(Level::WARNING, $srcCodeLine, $srcCodeFunc);
    }

    public function ifInfoLevelEnabled(int $srcCodeLine, string $srcCodeFunc): ?EnabledLoggerProxy
    {
        return $this->ifLevelEnabled(Level::INFO, $srcCodeLine, $srcCodeFunc);
    }

    public function ifDebugLevelEnabled(int $srcCodeLine, string $srcCodeFunc): ?EnabledLoggerProxy
    {
        return $this->ifLevelEnabled(Level::DEBUG, $srcCodeLine, $srcCodeFunc);
    }

    public function ifTraceLevelEnabled(int $srcCodeLine, string $srcCodeFunc): ?EnabledLoggerProxy
    {
        return $this->ifLevelEnabled(Level::TRACE, $srcCodeLine, $srcCodeFunc);
    }

    public function ifLevelEnabled(int $statementLevel, int $srcCodeLine, string $srcCodeFunc): ?EnabledLoggerProxy
    {
        return ($this->maxEnabledLevel >= $statementLevel)
            ? new EnabledLoggerProxy($statementLevel, $srcCodeLine, $srcCodeFunc, $this->data)
            : null;
    }

    /**
     * @param mixed $value
     *
     * @return mixed
     */
    public function possiblySecuritySensitive($value)
    {
        if ($this->maxEnabledLevel >= Level::TRACE) {
            return $value;
        }
        return 'HIDDEN POSSIBLY SECURITY SENSITIVE DATA';
    }
}
