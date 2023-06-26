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
use ElasticApmTests\ComponentTests\Util\AmbientContextForTests;
use ElasticApmTests\ComponentTests\Util\AppCodeHostParams;
use ElasticApmTests\ComponentTests\Util\AppCodeTarget;
use ElasticApmTests\ComponentTests\Util\ComponentTestCaseBase;
use ElasticApmTests\ComponentTests\Util\DockerUtil;
use ElasticApmTests\ComponentTests\Util\ExpectedEventCounts;
use ElasticApmTests\TestsSharedCode\MetadataDiscovererTestSharedCode;
use ElasticApmTests\Util\AssertMessageStack;
use ElasticApmTests\Util\MetadataExpectations;
use ElasticApmTests\Util\MetadataValidator;
use ElasticApmTests\Util\MixedMap;

/**
 * @group smoke
 * @group does_not_require_external_services
 */
final class MetadataDiscovererComponentTest extends ComponentTestCaseBase
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
            self::assertSame($expected, $metadata->service->environment);
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
            self::assertSame($expected, $metadata->service->name);
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
            self::assertSame($expected, $metadata->service->version);
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
        $expectedDetectedHostname = $expectedConfiguredHostname === null ? MetadataDiscoverer::discoverHostname() : null;

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
            self::assertSame($expected, $metadata->service->nodeConfiguredName);
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

    public function testContainerId(): void
    {
        self::runAndEscalateLogLevelOnFailure(
            self::buildDbgDescForTest(__CLASS__, __FUNCTION__),
            function (): void {
                $this->implTestContainerId();
            }
        );
    }

    private function implTestContainerId(): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, ['testConfig' => AmbientContextForTests::testConfig()]);

        if (AmbientContextForTests::testConfig()->isInContainer === null) {
            self::assertNull(AmbientContextForTests::testConfig()->thisContainerImageName);
            self::dummyAssert();
            return;
        }

        $testCaseHandle = $this->getTestCaseHandle();
        $appCodeHost = $testCaseHandle->ensureMainAppCodeHost();
        $appCodeHost->sendRequest(AppCodeTarget::asRouted([__CLASS__, 'appCodeEmpty']));
        $dataFromAgent = $this->waitForOneEmptyTransaction($testCaseHandle);
        $dbgCtx->add(['dataFromAgent' => $dataFromAgent]);
        $expectedContainerId = AmbientContextForTests::testConfig()->isInContainer ? DockerUtil::getThisContainerId() : null;
        foreach ($dataFromAgent->metadatas as $metadata) {
            self::assertSame($expectedContainerId, $metadata->system->containerId);
        }
    }

    /**
     * @return iterable<string, array{MixedMap}>
     */
    public static function dataProviderForTestGlobalLabels(): iterable
    {
        return MetadataDiscovererTestSharedCode::dataProviderForTestGlobalLabels(self::adaptToSmokeAsCallable());
    }

    /**
     * @dataProvider dataProviderForTestGlobalLabels
     */
    public function testGlobalLabelsDefaultConfig(MixedMap $testArgs): void
    {
        self::runAndEscalateLogLevelOnFailure(
            self::buildDbgDescForTestWithArtgs(__CLASS__, __FUNCTION__, $testArgs),
            function () use ($testArgs): void {
                $this->implTestGlobalLabelsDefaultConfig($testArgs);
            }
        );
    }

    private function implTestGlobalLabelsDefaultConfig(MixedMap $testArgs): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, ['testConfig' => AmbientContextForTests::testConfig()]);
        /** @var ?array<string|bool|int|float|null> $expectedLabels */
        $expectedLabels = $testArgs->getNullableArray(MetadataDiscovererTestSharedCode::EXPECTED_LABELS_KEY);

        MetadataExpectations::$labelsDefault->setValue($expectedLabels);

        $testCaseHandle = $this->getTestCaseHandle();

        $appCodeHost = $testCaseHandle->ensureMainAppCodeHost(
            function (AppCodeHostParams $appCodeParams) use ($testArgs): void {
                self::setConfigIfNotNull($testArgs, OptionNames::GLOBAL_LABELS, $appCodeParams);
            }
        );
        $appCodeHost->sendRequest(AppCodeTarget::asRouted([__CLASS__, 'appCodeEmpty']));

        $dataFromAgent = $testCaseHandle->waitForDataFromAgent((new ExpectedEventCounts())->transactions(1));
        MetadataDiscovererTestSharedCode::implTestGlobalLabelsAssertPart($expectedLabels, $dataFromAgent);
    }
}
