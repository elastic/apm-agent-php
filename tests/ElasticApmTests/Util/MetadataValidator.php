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

use Elastic\Apm\ElasticApm;
use Elastic\Apm\Impl\Metadata;
use Elastic\Apm\Impl\MetadataDiscoverer;
use Elastic\Apm\Impl\NameVersionData;
use Elastic\Apm\Impl\SystemData;
use PHPUnit\Framework\TestCase;

final class MetadataValidator extends DataValidatorBase
{
    /** @var MetadataExpectations */
    protected $expectations;

    /** @var Metadata */
    protected $actual;

    public static function validate(Metadata $actual, ?MetadataExpectations $expectations = null): void
    {
        (new self($expectations ?? new MetadataExpectations(), $actual))->validateImpl();
    }

    private function __construct(MetadataExpectations $expectations, Metadata $actual)
    {
        $this->expectations = $expectations;
        $this->actual = $actual;
    }

    private function validateImpl(): void
    {
        if ($this->expectations->agentEphemeralId !== null) {
            TestCase::assertNotNull($this->actual->service->agent);
            $this->validateNullableAgentEphemeralId($this->actual->service->agent->ephemeralId);
            TestCase::assertSame($this->expectations->agentEphemeralId, $this->actual->service->agent->ephemeralId);
        }

        $this->validateServiceData();
        $this->validateProcessData();
        $this->validateSystemData();
    }

    /**
     * @param mixed $agentEphemeralId
     *
     * @return ?string
     */
    public static function validateNullableAgentEphemeralId($agentEphemeralId): ?string
    {
        return self::validateNullableKeywordString($agentEphemeralId);
    }

    /**
     * @param mixed $pid
     *
     * @return int
     */
    public static function validateProcessId($pid): int
    {
        TestCase::assertIsInt($pid);
        /** @var int $pid */
        TestCase::assertGreaterThan(0, $pid);

        return $pid;
    }

    private function validateProcessData(): void
    {
        $processData = $this->actual->process;
        self::validateProcessId($processData->pid);
    }

    private static function validateNameVersionData(?NameVersionData $nameVersionData): void
    {
        if (is_null($nameVersionData)) {
            return;
        }
        self::validateNullableKeywordString($nameVersionData->name);
        self::validateNullableKeywordString($nameVersionData->version);
    }

    private function validateServiceData(): void
    {
        $serviceData = $this->actual->service;

        self::validateKeywordString($serviceData->name);
        TestCase::assertSame($this->expectations->serviceName, $serviceData->name);

        self::validateNullableKeywordString($serviceData->nodeConfiguredName);
        TestCase::assertSame($this->expectations->serviceNodeConfiguredName, $serviceData->nodeConfiguredName);

        self::validateNullableKeywordString($serviceData->version);
        TestCase::assertSame($this->expectations->serviceVersion, $serviceData->version);

        self::validateNullableKeywordString($serviceData->environment);
        TestCase::assertSame($this->expectations->serviceEnvironment, $serviceData->environment);

        self::validateNameVersionData($serviceData->agent);
        assert($serviceData->agent !== null);
        TestCase::assertTrue($serviceData->agent->name === MetadataDiscoverer::AGENT_NAME);
        TestCase::assertTrue($serviceData->agent->version === ElasticApm::VERSION);
        self::validateNullableKeywordString($serviceData->agent->ephemeralId);

        self::validateNameVersionData($serviceData->framework);

        self::validateNameVersionData($serviceData->language);
        assert($serviceData->language !== null);
        TestCase::assertTrue($serviceData->language->name === MetadataDiscoverer::LANGUAGE_NAME);

        self::validateNameVersionData($serviceData->runtime);
        TestCase::assertTrue($serviceData->runtime !== null);
        assert($serviceData->runtime !== null);
        TestCase::assertTrue($serviceData->runtime->name === MetadataDiscoverer::LANGUAGE_NAME);
        TestCase::assertTrue($serviceData->runtime->version === $serviceData->language->version);
    }

    private function validateSystemData(): void
    {
        $systemData = $this->actual->system;

        self::verifyHostnames(
            $this->expectations->configuredHostname,
            $this->expectations->detectedHostname,
            $systemData
        );
    }

    public static function verifyHostnames(
        ?string $expectedConfiguredHostname,
        ?string $expectedDetectedHostname,
        SystemData $systemData
    ): void {
        self::validateNullableKeywordString($systemData->hostname);
        self::validateNullableKeywordString($systemData->configuredHostname);
        self::validateNullableKeywordString($systemData->detectedHostname);

        if ($systemData->configuredHostname === null) {
            TestCase::assertSame($systemData->detectedHostname, $systemData->hostname);
        } else {
            TestCase::assertNull($systemData->detectedHostname);
            TestCase::assertSame($systemData->configuredHostname, $systemData->hostname);
        }

        TestCase::assertSame($expectedConfiguredHostname, $systemData->configuredHostname);
        TestCase::assertSame($expectedDetectedHostname, $systemData->detectedHostname);
    }

    public static function deriveExpectedServiceName(?string $configured): string
    {
        return $configured === null
            ? MetadataDiscoverer::DEFAULT_SERVICE_NAME
            : MetadataDiscoverer::adaptServiceName($configured);
    }
}
