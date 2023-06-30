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

use Elastic\Apm\Impl\Config\AllOptionsMetadata;
use Elastic\Apm\Impl\Config\CompositeRawSnapshotSource;
use Elastic\Apm\Impl\Config\EnvVarsRawSnapshotSource;
use Elastic\Apm\Impl\Config\IniRawSnapshotSource;
use Elastic\Apm\Impl\Config\Parser as ConfigParser;
use Elastic\Apm\Impl\Config\RawSnapshotSourceInterface as ConfigRawSnapshotSourceInterface;
use Elastic\Apm\Impl\Config\Snapshot as ConfigSnapshot;
use Elastic\Apm\Impl\Log\Backend as LogBackend;
use Elastic\Apm\Impl\Log\Level as LogLevel;
use Elastic\Apm\Impl\Log\LoggerFactory;
use Elastic\Apm\Impl\Log\SinkInterface as LogSinkInterface;
use Elastic\Apm\Impl\Util\ElasticApmExtensionUtil;
use Elastic\Apm\Impl\Util\HiddenConstructorTrait;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class TracerBuilder
{
    /**
     * Constructor is hidden because startNew() should be used instead
     */
    use HiddenConstructorTrait;

    /** @var TracerDependencies */
    private $tracerDependencies;

    /** @var bool */
    private $shouldCheckExtension;

    private function __construct(bool $shouldCheckExtension)
    {
        $this->tracerDependencies = new TracerDependencies();
        $this->shouldCheckExtension = $shouldCheckExtension;
    }

    public static function startNew(bool $shouldCheckExtension = true): self
    {
        return new self($shouldCheckExtension);
    }

    public function withClock(ClockInterface $clock): self
    {
        $this->tracerDependencies->clock = $clock;
        return $this;
    }

    public function withEventSink(EventSinkInterface $eventSink): self
    {
        $this->tracerDependencies->eventSink = $eventSink;
        return $this;
    }

    public function withLogSink(LogSinkInterface $logSink): self
    {
        $this->tracerDependencies->logSink = $logSink;
        return $this;
    }

    public function withConfigRawSnapshotSource(?ConfigRawSnapshotSourceInterface $configRawSnapshotSource): self
    {
        $this->tracerDependencies->configRawSnapshotSource = $configRawSnapshotSource;
        return $this;
    }

    public function build(): TracerInterface
    {
        if (
            $this->shouldCheckExtension
            && (!ElasticApmExtensionUtil::isLoaded() || !ElasticApmExtensionUtil::getEnabledConfig())
        ) {
            return NoopTracer::singletonInstance();
        }

        $config = self::buildConfig($this->tracerDependencies);
        return $config->enabled() ? new Tracer($this->tracerDependencies, $config) : NoopTracer::singletonInstance();
    }

    private static function buildConfig(TracerDependencies $providedDependencies): ConfigSnapshot
    {
        $rawSnapshotSource = $providedDependencies->configRawSnapshotSource
                             ?? new CompositeRawSnapshotSource(
                                 [
                                     new IniRawSnapshotSource(IniRawSnapshotSource::DEFAULT_PREFIX),
                                     new EnvVarsRawSnapshotSource(EnvVarsRawSnapshotSource::DEFAULT_NAME_PREFIX),
                                 ]
                             );

        $parsingLoggerFactory = new LoggerFactory(new LogBackend(LogLevel::TRACE, $providedDependencies->logSink));
        $parser = new ConfigParser($parsingLoggerFactory);
        $allOptsMeta = AllOptionsMetadata::get();
        $rawSnapshot = $rawSnapshotSource->currentSnapshot($allOptsMeta);
        return new ConfigSnapshot($parser->parse($allOptsMeta, $rawSnapshot), $parsingLoggerFactory);
    }
}
