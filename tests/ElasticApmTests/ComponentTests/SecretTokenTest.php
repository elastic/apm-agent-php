<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests;

use Elastic\Apm\Impl\Config\OptionNames;
use Elastic\Apm\Tests\ComponentTests\Util\AgentConfigSetterBase;
use Elastic\Apm\Tests\ComponentTests\Util\ComponentTestCaseBase;
use Elastic\Apm\Tests\ComponentTests\Util\DataFromAgent;
use Elastic\Apm\Tests\ComponentTests\Util\TestEnvBase;

final class SecretTokenTest extends ComponentTestCaseBase
{
    private function secretTokenConfigTestImpl(?AgentConfigSetterBase $configSetter, ?string $configured): void
    {
        $this->configTestImpl(
            $configSetter,
            $configured,
            function (AgentConfigSetterBase $configSetter, string $configured): void {
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
     * @param AgentConfigSetterBase $configSetter
     */
    public function testCustomSecretToken(AgentConfigSetterBase $configSetter): void
    {
        $this->secretTokenConfigTestImpl($configSetter, 'custom Secret TOKEN 9.8 @CI#!?');
    }
}
