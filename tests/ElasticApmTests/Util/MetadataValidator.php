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
use Elastic\Apm\Impl\MetricSetData;
use Elastic\Apm\Impl\NameVersionData;
use Elastic\Apm\Impl\ProcessData;
use Elastic\Apm\Impl\ServiceData;
use Elastic\Apm\Impl\SystemData;

final class MetadataValidator extends EventDataValidator
{
    /** @var MetadataExpected */
    protected $expected;

    /** @var Metadata */
    protected $actual;

    private function __construct(MetadataExpected $expected, Metadata $actual)
    {
        $this->expected = $expected;
        $this->actual = $actual;
    }

    private function validateImpl(): void
    {
        self::validateServiceData($this->actual->service);
        self::validateProcessData($this->actual->process);
        self::validateSystemData($this->actual->system);
    }

    public static function validate(Metadata $actual, ?MetadataExpected $expected = null): void
    {
        (new self($expected ?? new MetadataExpected(), $actual))->validateImpl();
    }

    /**
     * @param mixed $pid
     *
     * @return int
     */
    public static function validateProcessId($pid): int
    {
        self::assertIsInt($pid);
        /** @var int $pid */
        self::assertGreaterThan(0, $pid);

        return $pid;
    }

    public static function validateProcessData(ProcessData $processData): void
    {
        self::validateProcessId($processData->pid);
    }

    public static function validateNameVersionData(?NameVersionData $nameVersionData): void
    {
        if (is_null($nameVersionData)) {
            return;
        }
        self::validateNullableKeywordString($nameVersionData->name);
        self::validateNullableKeywordString($nameVersionData->version);
    }

    public static function validateServiceData(ServiceData $serviceData): void
    {
        self::validateKeywordString($serviceData->name);
        self::validateNullableKeywordString($serviceData->nodeConfiguredName);
        self::validateNullableKeywordString($serviceData->version);
        self::validateNullableKeywordString($serviceData->environment);

        self::validateNameVersionData($serviceData->agent);
        assert($serviceData->agent !== null);
        self::assertTrue($serviceData->agent->name === MetadataDiscoverer::AGENT_NAME);
        self::assertTrue($serviceData->agent->version === ElasticApm::VERSION);
        self::validateNullableKeywordString($serviceData->agent->ephemeralId);

        self::validateNameVersionData($serviceData->framework);

        self::validateNameVersionData($serviceData->language);
        assert($serviceData->language !== null);
        self::assertTrue($serviceData->language->name === MetadataDiscoverer::LANGUAGE_NAME);

        self::validateNameVersionData($serviceData->runtime);
        self::assertTrue($serviceData->runtime !== null);
        assert($serviceData->runtime !== null);
        self::assertTrue($serviceData->runtime->name === MetadataDiscoverer::LANGUAGE_NAME);
        self::assertTrue($serviceData->runtime->version === $serviceData->language->version);
    }

    public static function validateSystemData(SystemData $systemData): void
    {
        self::validateNullableKeywordString($systemData->hostname);
        self::validateNullableKeywordString($systemData->configuredHostname);
        self::validateNullableKeywordString($systemData->detectedHostname);

        if ($systemData->configuredHostname !== null) {
            self::assertTrue($systemData->detectedHostname === null);
            self::assertTrue($systemData->hostname === $systemData->configuredHostname);
        } else {
            self::assertTrue($systemData->hostname === $systemData->detectedHostname);
        }
    }
}
