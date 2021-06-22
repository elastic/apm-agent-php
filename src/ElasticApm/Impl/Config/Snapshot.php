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

namespace Elastic\Apm\Impl\Config;

use Elastic\Apm\Impl\Log\Level as LogLevel;
use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableTrait;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class Snapshot implements LoggableInterface
{
    use SnapshotTrait;
    use LoggableTrait;

    /** @var array<string, mixed> */
    private $optNameToParsedValue;

    /** @var string */
    private $apiKey;

    /** @var bool */
    private $breakdownMetrics;

    /** @var bool */
    private $enabled;

    /** @var string|null */
    private $environment;

    /** @var int|null */
    private $logLevel;

    /** @var int|null */
    private $logLevelStderr;

    /** @var int|null */
    private $logLevelSyslog;

    /** @var string */
    private $secretToken;

    /** @var float - In milliseconds */
    private $serverTimeout;

    /** @var string|null */
    private $serviceName;

    /** @var string|null */
    private $serviceVersion;

    /** @var int */
    private $transactionMaxSpans;

    /** @var float */
    private $transactionSampleRate;

    /** @var bool */
    private $verifyServerCert;

    /**
     * Snapshot constructor.
     *
     * @param array<string, mixed> $optNameToParsedValue
     */
    public function __construct(array $optNameToParsedValue)
    {
        $this->optNameToParsedValue = $optNameToParsedValue;
        $this->setPropertiesToValuesFrom($optNameToParsedValue);
    }

    /**
     * @param string $optName
     *
     * @return mixed
     */
    public function parsedValueFor(string $optName)
    {
        return $this->optNameToParsedValue[$optName];
    }

    public function breakdownMetrics(): bool
    {
        return $this->breakdownMetrics;
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function environment(): ?string
    {
        return $this->environment;
    }

    public function effectiveLogLevel(): int
    {
        $effectiveLogLevelStderr = ($this->logLevelStderr ?? $this->logLevel) ?? LogLevel::INFO;
        $effectiveLogLevelSyslog = ($this->logLevelSyslog ?? $this->logLevel) ?? LogLevel::CRITICAL;
        return max($effectiveLogLevelStderr, $effectiveLogLevelSyslog, $this->logLevel ?? LogLevel::OFF);
    }

    public function serverTimeout(): float
    {
        return $this->serverTimeout;
    }

    public function serviceName(): ?string
    {
        return $this->serviceName;
    }

    public function serviceVersion(): ?string
    {
        return $this->serviceVersion;
    }

    public function transactionMaxSpans(): int
    {
        return $this->transactionMaxSpans;
    }

    public function transactionSampleRate(): float
    {
        return $this->transactionSampleRate;
    }
}
