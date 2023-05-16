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
use ElasticApmTests\ComponentTests\Util\AppCodeHostParams;
use ElasticApmTests\ComponentTests\Util\AppCodeTarget;
use ElasticApmTests\ComponentTests\Util\ComponentTestCaseBase;
use ElasticApmTests\ComponentTests\Util\DataFromAgentPlusRawValidator;

/**
 * @group does_not_require_external_services
 */
final class ApiKeySecretTokenTest extends ComponentTestCaseBase
{
    private function apiKeyConfigTestImpl(?string $configuredApiKey, ?string $configuredSecretToken): void
    {
        $testCaseHandle = $this->getTestCaseHandle();
        $appCodeHost = $testCaseHandle->ensureMainAppCodeHost(
            function (AppCodeHostParams $appCodeParams) use ($configuredApiKey, $configuredSecretToken): void {
                if ($configuredApiKey !== null) {
                    $appCodeParams->setAgentOption(OptionNames::API_KEY, $configuredApiKey);
                }
                if ($configuredSecretToken !== null) {
                    $appCodeParams->setAgentOption(OptionNames::SECRET_TOKEN, $configuredSecretToken);
                }
            }
        );
        $appCodeHost->sendRequest(AppCodeTarget::asRouted([__CLASS__, 'appCodeEmpty']));
        $dataFromAgent = $this->waitForOneEmptyTransaction($testCaseHandle);
        foreach ($dataFromAgent->getAllIntakeApiRequests() as $intakeApiRequest) {
            DataFromAgentPlusRawValidator::verifyAuthIntakeApiHttpRequestHeader(
                $configuredApiKey,
                $configuredSecretToken,
                $intakeApiRequest->headers
            );
        }
    }

    public function testDefaultApiKey(): void
    {
        $this->apiKeyConfigTestImpl(/* configuredApiKey: */ null, /* configuredSecretToken: */ null);
    }

    public function testCustomApiKey(): void
    {
        $this->apiKeyConfigTestImpl('custom API Key 9.8 @CI#!?', /* configuredSecretToken: */ null);
    }

    public function testApiKeyTakesPrecedenceOverSecretToken(): void
    {
        $this->apiKeyConfigTestImpl('custom API Key', 'custom Secret TOKEN');
    }

    public function testSecretTokenIsUsedIfNoApiKey(): void
    {
        $this->apiKeyConfigTestImpl(/* configuredApiKey */ null, 'custom Secret TOKEN 9.8 @CI#!?');
    }
}
