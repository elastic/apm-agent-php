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

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace ElasticApmTests\UnitTests;

use Elastic\Apm\Impl\MetadataDiscoverer;
use ElasticApmTests\Util\TestCaseBase;

class MetadataTest extends TestCaseBase
{
    public function testDefaultServiceNameUsesAgentName(): void
    {
        // https://github.com/elastic/apm/blob/main/specs/agents/configuration.md#zero-configuration-support
        // ... the default value: unknown-${service.agent.name}-service ...
        self::assertSame(
            'unknown-' . MetadataDiscoverer::AGENT_NAME . '-service',
            MetadataDiscoverer::DEFAULT_SERVICE_NAME
        );
    }
}
