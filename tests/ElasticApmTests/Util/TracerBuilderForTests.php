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

namespace ElasticApmTests\Util;

use Elastic\Apm\Impl\ClockInterface;
use Elastic\Apm\Impl\Config\OptionNames;
use Elastic\Apm\Impl\EventSinkInterface;
use Elastic\Apm\Impl\Log\SinkInterface as LogSinkInterface;
use Elastic\Apm\Impl\TracerBuilder;
use Elastic\Apm\Impl\TracerInterface;
use ElasticApmTests\UnitTests\Util\MockConfigRawSnapshotSource;

final class TracerBuilderForTests
{
    /** @var TracerBuilder */
    private $wrappedTracerBuilder;

    /** @var MockConfigRawSnapshotSource */
    private $mockConfigRawSnapshotSource;

    private function __construct()
    {
        $this->wrappedTracerBuilder = TracerBuilder::startNew(/* shouldCheckExtension: */ false);

        $this->mockConfigRawSnapshotSource = new MockConfigRawSnapshotSource();
        $this->wrappedTracerBuilder->withConfigRawSnapshotSource($this->mockConfigRawSnapshotSource);
    }

    public static function startNew(): self
    {
        return new self();
    }

    public function withConfig(string $optName, string $optVal): self
    {
        $this->mockConfigRawSnapshotSource->set($optName, $optVal);
        return $this;
    }

    public function withBoolConfig(string $optName, bool $optVal): self
    {
        return $this->withConfig($optName, $optVal ? 'true' : 'false');
    }

    public function withEnabled(bool $isEnabled): self
    {
        return $this->withBoolConfig(OptionNames::ENABLED, $isEnabled);
    }

    public function withClock(ClockInterface $clock): self
    {
        $this->wrappedTracerBuilder->withClock($clock);
        return $this;
    }

    public function withEventSink(EventSinkInterface $eventSink): self
    {
        $this->wrappedTracerBuilder->withEventSink($eventSink);
        return $this;
    }

    public function withLogSink(LogSinkInterface $logSink): self
    {
        $this->wrappedTracerBuilder->withLogSink($logSink);
        return $this;
    }

    public function build(): TracerInterface
    {
        return $this->wrappedTracerBuilder->build();
    }
}
