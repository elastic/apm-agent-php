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

use Elastic\Apm\ElasticApm;
use Elastic\Apm\Impl\BackendComm\EventSender;
use Elastic\Apm\Impl\Config\OptionNames;
use Elastic\Apm\Impl\MetadataDiscoverer;
use ElasticApmTests\ComponentTests\Util\AppCodeHostParams;
use ElasticApmTests\ComponentTests\Util\AppCodeTarget;
use ElasticApmTests\ComponentTests\Util\ComponentTestCaseBase;
use ElasticApmTests\ComponentTests\Util\DataFromAgentPlusRawValidator;

/**
 * @group smoke
 * @group does_not_require_external_services
 */
final class UserAgentTest extends ComponentTestCaseBase
{
    private const AGENT_REPO_NAME_AND_VERSION = EventSender::AGENT_GITHUB_REPO_NAME . '/' . ElasticApm::VERSION;

    /**
     * @return iterable<array{?string, ?string, string}>
     */
    private static function dataProviderForTestCustomConfigImpl(): iterable
    {
        // https://github.com/elastic/apm/blob/main/specs/agents/transport.md#user-agent
        // Header value should start with agent github repository as prefix and version:
        // apm-agent-${language}/${agent.version}.
        // If both service.name and service.version are set, append (${service.name} ${service.version})
        // If only service.name is set, append (${service.name})
        //
        // Examples:
        //      apm-agent-java/v1.25.0
        //      apm-agent-ruby/4.4.0 (my_service)
        //      apm-agent-python/6.4.0 (my_service v42.7)

        yield [
            'My_serviceBa', // <- configuredServiceName
            null, // <- configuredServiceVersion
            self::AGENT_REPO_NAME_AND_VERSION . ' (My_serviceBa)' //
        ];

        yield [
            null, // <- configuredServiceName
            'v42.7', // <- configuredServiceVersion
            self::AGENT_REPO_NAME_AND_VERSION . ' (' . MetadataDiscoverer::DEFAULT_SERVICE_NAME . ' v42.7)'
        ];

        yield [
            'my Service', // <- configuredServiceName
            'v42.7', // <- configuredServiceVersion
            self::AGENT_REPO_NAME_AND_VERSION . ' (my Service v42.7)'
        ];

        $serviceNameWithIllegalChar = 'Service name with illegal char @';
        $serviceNameWithIllegalCharAdapted = 'Service name with illegal char _';
        $serviceVersionMaxKeywordStringLength = self::generateDummyMaxKeywordString();
        yield [
            $serviceNameWithIllegalChar, // <- configuredServiceName
            $serviceVersionMaxKeywordStringLength . '_tail', // <- configuredServiceVersion
            self::AGENT_REPO_NAME_AND_VERSION
            . ' (' . $serviceNameWithIllegalCharAdapted . ' ' . $serviceVersionMaxKeywordStringLength . ')'
        ];

        // From https://github.com/elastic/apm/blob/main/tests/agents/gherkin-specs/user_agent.feature

        $configuredServiceVersion = '123(:]\[;)456';
        $sanitizedServiceVersion = '123_:]_[;_456';
        yield [
            'myService', // <- configuredServiceName
            $configuredServiceVersion,
            self::AGENT_REPO_NAME_AND_VERSION . ' (myService ' . $sanitizedServiceVersion . ')'
        ];
    }

    /**
     * @return iterable<array{?string, ?string, string}>
     */
    public function dataProviderForTestCustomConfig(): iterable
    {
        return self::adaptToSmoke(self::dataProviderForTestCustomConfigImpl());
    }

    private function impl(
        ?string $configuredServiceName,
        ?string $configuredServiceVersion,
        string $expectedUserAgentHeaderValue
    ): void {
        $testCaseHandle = $this->getTestCaseHandle();
        $appCodeHost = $testCaseHandle->ensureMainAppCodeHost(
            function (AppCodeHostParams $appCodeParams) use ($configuredServiceName, $configuredServiceVersion): void {
                if ($configuredServiceName !== null) {
                    $appCodeParams->setAgentOption(OptionNames::SERVICE_NAME, $configuredServiceName);
                }
                if ($configuredServiceVersion !== null) {
                    $appCodeParams->setAgentOption(OptionNames::SERVICE_VERSION, $configuredServiceVersion);
                }
            }
        );
        $appCodeHost->sendRequest(AppCodeTarget::asRouted([__CLASS__, 'appCodeEmpty']));
        $dataFromAgent = $this->waitForOneEmptyTransaction($testCaseHandle);
        foreach ($dataFromAgent->getAllIntakeApiRequests() as $intakeApiRequest) {
            DataFromAgentPlusRawValidator::verifyUserAgentIntakeApiHttpRequestHeader(
                $expectedUserAgentHeaderValue,
                $intakeApiRequest->headers
            );
        }
    }

    public function testDefaultConfig(): void
    {
        $this->impl(
            null /* <- configuredServiceName */,
            null /* <- expectedUserAgentHeaderValue */,
            self::AGENT_REPO_NAME_AND_VERSION . ' (' . MetadataDiscoverer::DEFAULT_SERVICE_NAME . ')'
        );
    }

    /**
     * @dataProvider dataProviderForTestCustomConfig
     *
     * @param ?string $configuredServiceName
     * @param ?string $configuredServiceVersion
     * @param string  $expectedUserAgentHeaderValue
     */
    public function testCustomConfig(
        ?string $configuredServiceName,
        ?string $configuredServiceVersion,
        string $expectedUserAgentHeaderValue
    ): void {
        $this->impl(
            $configuredServiceName,
            $configuredServiceVersion,
            $expectedUserAgentHeaderValue
        );
    }
}
