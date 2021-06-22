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

final class SecretTokenTest extends ComponentTestCaseBase
{
    private function secretTokenConfigTestImpl(?AgentConfigSetter $configSetter, ?string $configured): void
    {
        $this->configTestImpl(
            $configSetter,
            $configured,
            function (AgentConfigSetter $configSetter, string $configured): void {
                $configSetter->set(OptionNames::SECRET_TOKEN, $configured);
            },
            function (DataFromAgent $dataFromAgent) use ($configured): void {
                TestEnvBase::verifyAuthHttpRequestHeaders(
                    null /* <- expectedApiKey */,
                    $configured /* <- expectedSecretToken */,
                    $dataFromAgent
                );
            }
        );
    }

    public function testDefaultSecretToken(): void
    {
        $this->secretTokenConfigTestImpl(/* configSetter: */ null, /* configured: */ null);
    }

    public function testCustomSecretToken(): void
    {
        $this->secretTokenConfigTestImpl($this->randomConfigSetter(), 'custom Secret TOKEN 9.8 @CI#!?');
    }
}
