<?php

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

    /**
     * @dataProvider configSetterTestDataProvider
     *
     * @param AgentConfigSetter $configSetter
     */
    public function testCustomSecretToken(AgentConfigSetter $configSetter): void
    {
        $this->secretTokenConfigTestImpl($configSetter, 'custom Secret TOKEN 9.8 @CI#!?');
    }
}
