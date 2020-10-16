<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests;

use Elastic\Apm\Impl\Config\OptionNames;
use Elastic\Apm\Tests\ComponentTests\Util\ComponentTestCaseBase;
use Elastic\Apm\Tests\ComponentTests\Util\ConfigSetterBase;
use Elastic\Apm\Tests\ComponentTests\Util\DataFromAgent;
use Elastic\Apm\Tests\ComponentTests\Util\TestEnvBase;
use Elastic\Apm\Tests\ComponentTests\Util\TestProperties;

final class ApiKeyTest extends ComponentTestCaseBase
{
    private function apiKeyConfigTestImpl(
        ?ConfigSetterBase $configSetter,
        ?string $configuredApiKey,
        ?string $configuredSecretToken
    ): void {
        $testProperties = new TestProperties([__CLASS__, 'appCodeEmpty']);
        if (!is_null($configSetter)) {
            self::assertTrue(!is_null($configuredApiKey) || !is_null($configuredSecretToken));
            $testProperties->withConfigSetter($configSetter);
            if (!is_null($configuredApiKey)) {
                $configSetter->setOption(OptionNames::API_KEY, $configuredApiKey);
            }
            if (!is_null($configuredSecretToken)) {
                $configSetter->setOption(OptionNames::SECRET_TOKEN, $configuredSecretToken);
            }
        }
        $this->sendRequestToInstrumentedAppAndVerifyDataFromAgentEx(
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
     * @param ConfigSetterBase $configSetter
     */
    public function testCustomApiKey(ConfigSetterBase $configSetter): void
    {
        $this->apiKeyConfigTestImpl($configSetter, 'custom API Key 9.8 @CI#!?', /* configuredSecretToken: */ null);
    }

    /**
     * @dataProvider configSetterTestDataProvider
     *
     * @param ConfigSetterBase $configSetter
     */
    public function testApiKeyTakesPrecedenceOverSecretToken(ConfigSetterBase $configSetter): void
    {
        $this->apiKeyConfigTestImpl($configSetter, 'custom API Key', 'custom Secret TOKEN');
    }

    /**
     * @dataProvider configSetterTestDataProvider
     *
     * @param ConfigSetterBase $configSetter
     */
    public function testSecretTokenIsUsedIfNoApiKey(ConfigSetterBase $configSetter): void
    {
        $this->apiKeyConfigTestImpl($configSetter, /* configuredApiKey */ null, 'custom Secret TOKEN');
    }
}
