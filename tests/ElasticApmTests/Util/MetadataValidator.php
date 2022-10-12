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
use Elastic\Apm\Impl\ProcessData;
use Elastic\Apm\Impl\ServiceAgentData;
use Elastic\Apm\Impl\ServiceData;
use Elastic\Apm\Impl\SystemData;
use PHPUnit\Framework\TestCase;

final class MetadataValidator
{
    use AssertValidTrait;

    /** @var MetadataExpectations */
    protected $expectations;

    /** @var Metadata */
    protected $actual;

    public static function assertValid(Metadata $actual, ?MetadataExpectations $expectations = null): void
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
        $this->validateProcessData($this->actual->process);
        $this->validateServiceData();
        $this->validateSystemData();
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

    public static function validateProcessData(ProcessData $processData): void
    {
        self::validateProcessId($processData->pid);
    }

    private function validateServiceData(): void
    {
        $expected = $this->expectations;
        $actual = $this->actual->service;
        self::validateServiceDataEx($actual);

        $this->validateServiceAgentData();

        TestCaseBase::assertSameExpectedOptional($expected->serviceName, $actual->name);
        TestCaseBase::assertSameExpectedOptional($expected->serviceNodeConfiguredName, $actual->nodeConfiguredName);
        TestCaseBase::assertSameExpectedOptional($expected->serviceVersion, $actual->version);
        TestCaseBase::assertSameExpectedOptional($expected->serviceEnvironment, $actual->environment);
    }

    public static function validateServiceDataEx(ServiceData $serviceData): void
    {
        self::assertValidKeywordString($serviceData->name);
        self::assertValidNullableKeywordString($serviceData->nodeConfiguredName);
        self::assertValidNullableKeywordString($serviceData->version);
        self::assertValidNullableKeywordString($serviceData->environment);

        if ($serviceData->agent !== null) {
            self::validateServiceAgentDataEx($serviceData->agent);
        }

        self::validateNullableNameVersionData($serviceData->framework);

        self::validateNullableNameVersionData($serviceData->language);
        TestCase::assertNotNull($serviceData->language);
        TestCase::assertSame(MetadataDiscoverer::LANGUAGE_NAME, $serviceData->language->name);

        self::validateNullableNameVersionData($serviceData->runtime);
        TestCase::assertNotNull($serviceData->runtime);
        TestCase::assertSame(MetadataDiscoverer::LANGUAGE_NAME, $serviceData->runtime->name);
        TestCase::assertSame($serviceData->language->version, $serviceData->runtime->version);
    }

    private function validateServiceAgentData(): void
    {
        $expected = $this->expectations;
        $actual = $this->actual->service->agent;
        if ($actual === null) {
            TestCase::assertTrue(
                !$expected->agentEphemeralId->isValueSet()
                || $expected->agentEphemeralId->getValue() === null
            );
            return;
        }
        self::validateServiceAgentDataEx($actual);

        TestCaseBase::assertSameExpectedOptional($expected->agentEphemeralId, $actual->ephemeralId);
    }

    public static function validateServiceAgentDataEx(ServiceAgentData $serviceAgentData): void
    {
        self::validateNullableNameVersionData($serviceAgentData);
        TestCase::assertSame(MetadataDiscoverer::AGENT_NAME, $serviceAgentData->name);
        TestCase::assertSame(ElasticApm::VERSION, $serviceAgentData->version);
        self::assertValidNullableKeywordString($serviceAgentData->ephemeralId);
    }

    public static function validateSystemDataEx(SystemData $systemData): void
    {
        self::assertValidNullableKeywordString($systemData->hostname);
        self::assertValidNullableKeywordString($systemData->configuredHostname);
        self::assertValidNullableKeywordString($systemData->detectedHostname);
        if ($systemData->configuredHostname === null) {
            TestCase::assertSame($systemData->detectedHostname, $systemData->hostname);
        } else {
            TestCase::assertNull($systemData->detectedHostname);
            TestCase::assertSame($systemData->configuredHostname, $systemData->hostname);
        }
    }

    private function validateSystemData(): void
    {
        self::validateSystemDataEx($this->actual->system);
        TestCase::assertSame(
            $this->expectations->configuredHostname->isValueSet(),
            $this->expectations->detectedHostname->isValueSet()
        );
        if ($this->expectations->configuredHostname->isValueSet()) {
            self::verifyHostnames(
                $this->expectations->configuredHostname->getValue(),
                $this->expectations->detectedHostname->getValue(),
                $this->actual->system
            );
        }
    }

    public static function verifyHostnames(
        ?string $expectedConfiguredHostname,
        ?string $expectedDetectedHostname,
        SystemData $systemData
    ): void {

        TestCase::assertSame($expectedConfiguredHostname, $systemData->configuredHostname);
        TestCase::assertSame($expectedDetectedHostname, $systemData->detectedHostname);
    }

    public static function deriveExpectedServiceName(?string $configured): string
    {
        return $configured === null
            ? MetadataDiscoverer::DEFAULT_SERVICE_NAME
            : MetadataDiscoverer::adaptServiceName($configured);
    }

    public static function validateNullableNameVersionData(?NameVersionData $nameVersionData): void
    {
        if ($nameVersionData === null) {
            return;
        }
        self::assertValidNullableKeywordString($nameVersionData->name);
        self::assertValidNullableKeywordString($nameVersionData->version);
    }
}
