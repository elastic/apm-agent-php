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
use ElasticApmTests\ComponentTests\Util\AgentConfigSetter;
use ElasticApmTests\ComponentTests\Util\ComponentTestCaseBase;
use ElasticApmTests\ComponentTests\Util\DataFromAgent;
use ElasticApmTests\ComponentTests\Util\TestEnvBase;
use ElasticApmTests\ComponentTests\Util\TestProperties;

final class ApiKeyTest extends ComponentTestCaseBase
{
    private function apiKeyConfigTestImpl(
        ?AgentConfigSetter $configSetter,
        ?string $configuredApiKey,
        ?string $configuredSecretToken
    ): void {
        $testProperties = (new TestProperties())->withRoutedAppCode([__CLASS__, 'appCodeEmpty']);
        if (!is_null($configSetter)) {
            self::assertTrue(!is_null($configuredApiKey) || !is_null($configuredSecretToken));
            if (!is_null($configuredApiKey)) {
                $configSetter->set(OptionNames::API_KEY, $configuredApiKey);
            }
            if (!is_null($configuredSecretToken)) {
                $configSetter->set(OptionNames::SECRET_TOKEN, $configuredSecretToken);
            }
            $testProperties->withAgentConfig($configSetter);
        }
        $this->sendRequestToInstrumentedAppAndVerifyDataFromAgent(
            $testProperties,
            function (DataFromAgent $dataFromAgent) use ($configuredApiKey, $configuredSecretToken): void {
                TestEnvBase::verifyAuthHttpRequestHeaders(
                    $configuredApiKey /* <- expectedApiKey */,
                    is_null($configuredApiKey) ? $configuredSecretToken : null /* <- expectedSecretToken */,
                    $dataFromAgent
                );
            }
        );
    }

    public function testDefaultApiKey(): void
    {
        $this->apiKeyConfigTestImpl(
            null /* <- configSetter */,
            null /* <- configuredApiKey */,
            null /* <- configuredSecretToken */
        );
    }

    /**
     * @dataProvider configSetterTestDataProvider
     *
     * @param AgentConfigSetter $configSetter
     */
    public function testCustomApiKey(AgentConfigSetter $configSetter): void
    {
        $this->apiKeyConfigTestImpl($configSetter, 'custom API Key 9.8 @CI#!?', /* configuredSecretToken: */ null);
    }

    /**
     * @dataProvider configSetterTestDataProvider
     *
     * @param AgentConfigSetter $configSetter
     */
    public function testApiKeyTakesPrecedenceOverSecretToken(AgentConfigSetter $configSetter): void
    {
        $this->apiKeyConfigTestImpl($configSetter, 'custom API Key', 'custom Secret TOKEN');
    }

    /**
     * @dataProvider configSetterTestDataProvider
     *
     * @param AgentConfigSetter $configSetter
     */
    public function testSecretTokenIsUsedIfNoApiKey(AgentConfigSetter $configSetter): void
    {
        $this->apiKeyConfigTestImpl($configSetter, /* configuredApiKey */ null, 'custom Secret TOKEN');
    }
}
