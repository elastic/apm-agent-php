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

namespace ElasticApmTests\ComponentTests;

use Elastic\Apm\Impl\Config\OptionNames;
use Elastic\Apm\Impl\Constants;
use Elastic\Apm\Impl\MetadataDiscoverer;
use Elastic\Apm\Impl\Tracer;
use ElasticApmTests\ComponentTests\Util\ComponentTestCaseBase;
use ElasticApmTests\Util\MetadataValidator;
use PHPUnit\Framework\TestCase;

/**
 * @group smoke
 * @group does_not_require_external_services
 */
final class MetadataTest extends ComponentTestCaseBase
{
    /**
     * @return iterable<array{string}>
     */
    public function dataProviderForConfigOptionValueTest(): iterable
    {
        return self::adaptToSmoke(
            [
                ['custom value 1.2,3 @CI#!?'],
                [
                    '['
                    . str_repeat('A', (Constants::KEYWORD_STRING_MAX_LENGTH - 4) / 2)
                    . ','
                    . ';'
                    . str_repeat('B', (Constants::KEYWORD_STRING_MAX_LENGTH - 4) / 2)
                    . ']'
                    . '_tail',
                ]
            ]
        );
    }

    private function environmentConfigTestImpl(?string $configured, ?string $expected): void
    {
        $dataFromAgent = $this->configTestImpl(OptionNames::ENVIRONMENT, $configured);
        foreach ($dataFromAgent->metadatas as $metadata) {
            TestCase::assertSame($expected, $metadata->service->environment);
        }
    }

    public function testDefaultEnvironment(): void
    {
        $this->environmentConfigTestImpl(/* configured: */ null, /* expected: */ null);
    }

    /**
     * @dataProvider dataProviderForConfigOptionValueTest
     *
     * @param string $configured
     */
    public function testCustomEnvironment(string $configured): void
    {
        $this->environmentConfigTestImpl($configured, /* expected: */ Tracer::limitKeywordString($configured));
    }

    public function testInvalidEnvironmentTooLong(): void
    {
        $expected = self::generateDummyMaxKeywordString();
        $this->environmentConfigTestImpl(/* configured: */ $expected . '_tail', $expected);
    }

    private function serviceNameConfigTestImpl(?string $configured, string $expected): void
    {
        $dataFromAgent = $this->configTestImpl(OptionNames::SERVICE_NAME, $configured);
        foreach ($dataFromAgent->metadatas as $metadata) {
            TestCase::assertSame($expected, $metadata->service->name);
        }
    }

    public function testDefaultServiceName(): void
    {
        $this->serviceNameConfigTestImpl(
            null /* <- configured */,
            MetadataDiscoverer::DEFAULT_SERVICE_NAME /* <- expected */
        );
    }

    public function testCustomServiceName(): void
    {
        $configured = 'custom service name';
        $this->serviceNameConfigTestImpl($configured, /* expected: */ $configured);
    }

    public function testInvalidServiceNameChars(): void
    {
        $this->serviceNameConfigTestImpl(
            '1CUSTOM -@- sErvIcE -+- NaMe9' /* <- configured */,
            '1CUSTOM -_- sErvIcE -_- NaMe9' /* <- expected */
        );
    }

    public function testInvalidServiceNameTooLong(): void
    {
        $this->serviceNameConfigTestImpl(
            /* configured: */
            '[' . str_repeat('A', (Constants::KEYWORD_STRING_MAX_LENGTH - 4) / 2)
            . ','
            . ';'
            . str_repeat('B', (Constants::KEYWORD_STRING_MAX_LENGTH - 4) / 2) . ']' . '_tail',
            /* expected: */
            '_' . str_repeat('A', Constants::KEYWORD_STRING_MAX_LENGTH / 2 - 2)
            . '_'
            . '_'
            . str_repeat('B', Constants::KEYWORD_STRING_MAX_LENGTH / 2 - 2) . '_'
        );
    }

    private function serviceVersionConfigTestImpl(?string $configured, ?string $expected): void
    {
        $dataFromAgent = $this->configTestImpl(OptionNames::SERVICE_VERSION, $configured);
        foreach ($dataFromAgent->metadatas as $metadata) {
            TestCase::assertSame($expected, $metadata->service->version);
        }
    }

    public function testDefaultServiceVersion(): void
    {
        $this->serviceVersionConfigTestImpl(/* configured: */ null, /* expected: */ null);
    }

    public function testCustomServiceVersion(): void
    {
        $configured = 'v1.5.4-alpha@CI#.!?.';
        $this->serviceVersionConfigTestImpl($configured, /* expected: */ $configured);
    }

    public function testInvalidServiceVersionTooLong(): void
    {
        $expected = self::generateDummyMaxKeywordString();
        $this->serviceVersionConfigTestImpl(/* configured: */ $expected . '_tail', $expected);
    }

    private function hostnameConfigTestImpl(?string $configured): void
    {
        $dataFromAgent = $this->configTestImpl(OptionNames::HOSTNAME, $configured);

        $expectedConfiguredHostname = Tracer::limitNullableKeywordString($configured);
        $expectedDetectedHostname
            = $expectedConfiguredHostname === null ? MetadataDiscoverer::detectHostname() : null;

        foreach ($dataFromAgent->metadatas as $metadata) {
            MetadataValidator::verifyHostnames(
                $expectedConfiguredHostname,
                $expectedDetectedHostname,
                $metadata->system
            );
        }
    }

    public function testDefaultHostname(): void
    {
        $this->hostnameConfigTestImpl(/* configured: */ null);
    }

    /**
     * @dataProvider dataProviderForConfigOptionValueTest
     *
     * @param string $configured
     */
    public function testCustomHostname(string $configured): void
    {
        $this->hostnameConfigTestImpl($configured);
    }

    private function serviceNodeNameConfigTestImpl(?string $configured, ?string $expected): void
    {
        $dataFromAgent = $this->configTestImpl(OptionNames::SERVICE_NODE_NAME, $configured);
        foreach ($dataFromAgent->metadatas as $metadata) {
            TestCase::assertSame($expected, $metadata->service->nodeConfiguredName);
        }
    }

    public function testDefaultServiceNodeName(): void
    {
        $this->serviceNodeNameConfigTestImpl(/* configured: */ null, /* expected: */ null);
    }

    public function testCustomServiceNodeName(): void
    {
        $configured = 'alpha-centauri node@CI#.!?.';
        $this->serviceNodeNameConfigTestImpl($configured, /* expected: */ $configured);
    }

    public function testInvalidServiceNodeNameTooLong(): void
    {
        $expected = self::generateDummyMaxKeywordString();
        $this->serviceNodeNameConfigTestImpl(/* configured: */ $expected . '_tail', $expected);
    }
}
