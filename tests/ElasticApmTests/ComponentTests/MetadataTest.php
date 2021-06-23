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
use ElasticApmTests\ComponentTests\Util\AgentConfigSetter;
use ElasticApmTests\ComponentTests\Util\ComponentTestCaseBase;
use ElasticApmTests\ComponentTests\Util\DataFromAgent;
use ElasticApmTests\ComponentTests\Util\TestEnvBase;

final class MetadataTest extends ComponentTestCaseBase
{
    /**
     * @return iterable<array{string}>
     */
    public function configOptionValueTestDataProvider(): iterable
    {
        yield ['custom value 1.2,3 @CI#!?'];
        yield [
            '['
            . str_repeat('A', (Constants::KEYWORD_STRING_MAX_LENGTH - 4) / 2)
            . ','
            . ';'
            . str_repeat('B', (Constants::KEYWORD_STRING_MAX_LENGTH - 4) / 2)
            . ']'
            . '_tail',
        ];
    }

    private function environmentConfigTestImpl(
        ?AgentConfigSetter $configSetter,
        ?string $configured,
        ?string $expected
    ): void {
        $this->configTestImpl(
            $configSetter,
            $configured,
            function (AgentConfigSetter $configSetter, string $configured): void {
                $configSetter->set(OptionNames::ENVIRONMENT, $configured);
            },
            function (DataFromAgent $dataFromAgent) use ($expected): void {
                TestEnvBase::verifyEnvironment($expected, $dataFromAgent);
            }
        );
    }

    public function testDefaultEnvironment(): void
    {
        $this->environmentConfigTestImpl(/* configSetter: */ null, /* configured: */ null, /* expected: */ null);
    }

    /**
     * @dataProvider configOptionValueTestDataProvider
     *
     * @param string $configured
     */
    public function testCustomEnvironment(string $configured): void
    {
        $this->environmentConfigTestImpl(
            $this->randomConfigSetter(),
            $configured, /* expected: */
            Tracer::limitKeywordString($configured)
        );
    }

    public function testInvalidEnvironmentTooLong(): void
    {
        $expected = self::generateDummyMaxKeywordString();
        $this->environmentConfigTestImpl($this->randomConfigSetter(), /* configured: */ $expected . '_tail', $expected);
    }

    private function serviceNameConfigTestImpl(
        ?AgentConfigSetter $configSetter,
        ?string $configured,
        string $expected
    ): void {
        $this->configTestImpl(
            $configSetter,
            $configured,
            function (AgentConfigSetter $configSetter, string $configured): void {
                $configSetter->set(OptionNames::SERVICE_NAME, $configured);
            },
            function (DataFromAgent $dataFromAgent) use ($expected): void {
                TestEnvBase::verifyServiceName($expected, $dataFromAgent);
            }
        );
    }

    public function testDefaultServiceName(): void
    {
        $this->serviceNameConfigTestImpl(
            null /* <- configSetter */,
            null /* <- configured */,
            MetadataDiscoverer::DEFAULT_SERVICE_NAME /* <- expected */
        );
    }

    public function testCustomServiceName(): void
    {
        $configured = 'custom service name';
        $this->serviceNameConfigTestImpl($this->randomConfigSetter(), $configured, /* expected: */ $configured);
    }

    public function testInvalidServiceNameChars(): void
    {
        $this->serviceNameConfigTestImpl(
            $this->randomConfigSetter(),
            /* configured: */ '1CUSTOM -@- sErvIcE -+- NaMe9',
            /* expected:   */ '1CUSTOM -_- sErvIcE -_- NaMe9'
        );
    }

    public function testInvalidServiceNameTooLong(): void
    {
        $this->serviceNameConfigTestImpl(
            $this->randomConfigSetter(),
            /* configured: */ '[' . str_repeat('A', (Constants::KEYWORD_STRING_MAX_LENGTH - 4) / 2)
                              . ','
                              . ';'
                              . str_repeat('B', (Constants::KEYWORD_STRING_MAX_LENGTH - 4) / 2) . ']' . '_tail',
            /* expected:   */ '_' . str_repeat('A', Constants::KEYWORD_STRING_MAX_LENGTH / 2 - 2)
                              . '_'
                              . '_'
                              . str_repeat('B', Constants::KEYWORD_STRING_MAX_LENGTH / 2 - 2) . '_'
        );
    }

    private function serviceVersionConfigTestImpl(
        ?AgentConfigSetter $configSetter,
        ?string $configured,
        ?string $expected
    ): void {
        $this->configTestImpl(
            $configSetter,
            $configured,
            function (AgentConfigSetter $configSetter, string $configured): void {
                $configSetter->set(OptionNames::SERVICE_VERSION, $configured);
            },
            function (DataFromAgent $dataFromAgent) use ($expected): void {
                TestEnvBase::verifyServiceVersion($expected, $dataFromAgent);
            }
        );
    }

    public function testDefaultServiceVersion(): void
    {
        $this->serviceVersionConfigTestImpl(/* configSetter: */ null, /* configured: */ null, /* expected: */ null);
    }

    public function testCustomServiceVersion(): void
    {
        $configured = 'v1.5.4-alpha@CI#.!?.';
        $this->serviceVersionConfigTestImpl($this->randomConfigSetter(), $configured, /* expected: */ $configured);
    }

    public function testInvalidServiceVersionTooLong(): void
    {
        $expected = self::generateDummyMaxKeywordString();
        $this->serviceVersionConfigTestImpl(
            $this->randomConfigSetter(),
            /* configured: */ $expected . '_tail',
            $expected
        );
    }

    private function hostnameConfigTestImpl(?AgentConfigSetter $configSetter, ?string $configured): void
    {
        $this->configTestImpl(
            $configSetter,
            $configured,
            function (AgentConfigSetter $configSetter, string $configured): void {
                $configSetter->set(OptionNames::HOSTNAME, $configured);
            },
            function (DataFromAgent $dataFromAgent) use ($configured): void {
                TestEnvBase::verifyHostname($configured, $dataFromAgent);
            }
        );
    }

    public function testDefaultHostname(): void
    {
        $this->hostnameConfigTestImpl(/* configSetter: */ null, /* configured: */ null);
    }

    /**
     * @dataProvider configOptionValueTestDataProvider
     *
     * @param string $configured
     */
    public function testCustomHostname(string $configured): void
    {
        $this->hostnameConfigTestImpl($this->randomConfigSetter(), $configured);
    }
}
