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

use Elastic\Apm\Impl\Config\RawSnapshotSourceInterface as ConfigRawSnapshotSourceInterface;
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

    /** @var bool */
    private $isEnabled = true;

    /** @var TracerDependencies */
    private $tracerDependencies;

    private function __construct()
    {
        $this->tracerDependencies = new TracerDependencies();
    }

    public static function startNew(): self
    {
        return new self();
    }

    public function withEnabled(bool $isEnabled): self
    {
        $this->isEnabled = $isEnabled;
        return $this;
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

    public function withLogSink(Log\SinkInterface $logSink): self
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
        if (!$this->isEnabled) {
            return NoopTracer::singletonInstance();
        }

        return new Tracer($this->tracerDependencies);
    }
}
