<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests;

use Elastic\Apm\Impl\Config\OptionNames;
use Elastic\Apm\Tests\ComponentTests\Util\AgentConfigSetterBase;
use Elastic\Apm\Tests\ComponentTests\Util\ComponentTestCaseBase;
use Elastic\Apm\Tests\ComponentTests\Util\DataFromAgent;
use Elastic\Apm\Tests\ComponentTests\Util\TestEnvBase;
use Elastic\Apm\Tests\ComponentTests\Util\TestProperties;

final class ApiKeyTest extends ComponentTestCaseBase
{
    private function apiKeyConfigTestImpl(
        ?AgentConfigSetterBase $configSetter,
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
     * @param AgentConfigSetterBase $configSetter
     */
    public function testCustomApiKey(AgentConfigSetterBase $configSetter): void
    {
        $this->apiKeyConfigTestImpl($configSetter, 'custom API Key 9.8 @CI#!?', /* configuredSecretToken: */ null);
    }

    /**
     * @dataProvider configSetterTestDataProvider
     *
     * @param AgentConfigSetterBase $configSetter
     */
    public function testApiKeyTakesPrecedenceOverSecretToken(AgentConfigSetterBase $configSetter): void
    {
        $this->apiKeyConfigTestImpl($configSetter, 'custom API Key', 'custom Secret TOKEN');
    }

    /**
     * @dataProvider configSetterTestDataProvider
     *
     * @param AgentConfigSetterBase $configSetter
     */
    public function testSecretTokenIsUsedIfNoApiKey(AgentConfigSetterBase $configSetter): void
    {
        $this->apiKeyConfigTestImpl($configSetter, /* configuredApiKey */ null, 'custom Secret TOKEN');
    }
}
