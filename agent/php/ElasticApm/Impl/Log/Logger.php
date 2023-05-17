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
final class Logger implements LoggableInterface
{
    use LoggableTrait;

    /** @var LoggerData */
    private $data;

    private function __construct(LoggerData $data)
    {
        $this->data = $data;
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
        return new self(LoggerData::makeRoot($category, $namespace, $fqClassName, $srcCodeFile, $backend));
    }

    public function inherit(): self
    {
        return new self($this->data->inherit());
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

    public function ifTraceLevelEnabledNoLine(string $srcCodeFunc): ?EnabledLoggerProxyNoLine
    {
        return $this->ifLevelEnabledNoLine(Level::TRACE, $srcCodeFunc);
    }

    public function ifLevelEnabled(int $statementLevel, int $srcCodeLine, string $srcCodeFunc): ?EnabledLoggerProxy
    {
        return ($this->data->backend->isEnabledForLevel($statementLevel))
            ? new EnabledLoggerProxy($statementLevel, $srcCodeLine, $srcCodeFunc, $this->data)
            : null;
    }

    public function ifLevelEnabledNoLine(int $statementLevel, string $srcCodeFunc): ?EnabledLoggerProxyNoLine
    {
        return ($this->data->backend->isEnabledForLevel($statementLevel))
            ? new EnabledLoggerProxyNoLine($statementLevel, $srcCodeFunc, $this->data)
            : null;
    }

    /**
     * @param mixed $value
     *
     * @return mixed
     */
    public function possiblySecuritySensitive($value)
    {
        if ($this->data->backend->isEnabledForLevel(Level::TRACE)) {
            return $value;
        }
        return 'REDUCTED (POSSIBLY SECURITY SENSITIVE) DATA';
    }
}
