<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests;

use Elastic\Apm\Impl\Config\OptionNames;
use Elastic\Apm\Impl\Constants;
use Elastic\Apm\Impl\MetadataDiscoverer;
use Elastic\Apm\Tests\ComponentTests\Util\AgentConfigSetterBase;
use Elastic\Apm\Tests\ComponentTests\Util\ComponentTestCaseBase;
use Elastic\Apm\Tests\ComponentTests\Util\DataFromAgent;
use Elastic\Apm\Tests\ComponentTests\Util\TestEnvBase;

final class MetadataTest extends ComponentTestCaseBase
{
    private static function generateDummyMaxKeywordString(): string
    {
        return '[' . str_repeat('V', (Constants::KEYWORD_STRING_MAX_LENGTH - 4) / 2)
               . ','
               . ';'
               . str_repeat('W', (Constants::KEYWORD_STRING_MAX_LENGTH - 4) / 2) . ']';
    }

    private function environmentConfigTestImpl(
        ?AgentConfigSetterBase $configSetter,
        ?string $configured,
        ?string $expected
    ): void {
        $this->configTestImpl(
            $configSetter,
            $configured,
            function (AgentConfigSetterBase $configSetter, string $configured): void {
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
     * @dataProvider configSetterTestDataProvider
     *
     * @param AgentConfigSetterBase $configSetter
     */
    public function testCustomEnvironment(AgentConfigSetterBase $configSetter): void
    {
        $configured = 'custom service environment 9.8 @CI#!?';
        $this->environmentConfigTestImpl($configSetter, $configured, /* expected: */ $configured);
    }

    /**
     * @dataProvider configSetterTestDataProvider
     *
     * @param AgentConfigSetterBase $configSetter
     */
    public function testInvalidEnvironmentTooLong(AgentConfigSetterBase $configSetter): void
    {
        $expected = self::generateDummyMaxKeywordString();
        $this->environmentConfigTestImpl($configSetter, /* configured: */ $expected . '_tail', $expected);
    }

    private function serviceNameConfigTestImpl(
        ?AgentConfigSetterBase $configSetter,
        ?string $configured,
        string $expected
    ): void {
        $this->configTestImpl(
            $configSetter,
            $configured,
            function (AgentConfigSetterBase $configSetter, string $configured): void {
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

    /**
     * @dataProvider configSetterTestDataProvider
     *
     * @param AgentConfigSetterBase $configSetter
     */
    public function testCustomServiceName(AgentConfigSetterBase $configSetter): void
    {
        $configured = 'custom service name';
        $this->serviceNameConfigTestImpl($configSetter, $configured, /* expected: */ $configured);
    }

    /**
     * @dataProvider configSetterTestDataProvider
     *
     * @param AgentConfigSetterBase $configSetter
     */
    public function testInvalidServiceNameChars(AgentConfigSetterBase $configSetter): void
    {
        $this->serviceNameConfigTestImpl(
            $configSetter,
            /* configured: */ '1CUSTOM -@- sErvIcE -+- NaMe9',
            /* expected:   */ '1CUSTOM -_- sErvIcE -_- NaMe9'
        );
    }

    /**
     * @dataProvider configSetterTestDataProvider
     *
     * @param AgentConfigSetterBase $configSetter
     */
    public function testInvalidServiceNameTooLong(AgentConfigSetterBase $configSetter): void
    {
        $this->serviceNameConfigTestImpl(
            $configSetter,
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
        ?AgentConfigSetterBase $configSetter,
        ?string $configured,
        ?string $expected
    ): void {
        $this->configTestImpl(
            $configSetter,
            $configured,
            function (AgentConfigSetterBase $configSetter, string $configured): void {
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

    /**
     * @dataProvider configSetterTestDataProvider
     *
     * @param AgentConfigSetterBase $configSetter
     */
    public function testCustomServiceVersion(AgentConfigSetterBase $configSetter): void
    {
        $configured = 'v1.5.4-alpha@CI#.!?.';
        $this->serviceVersionConfigTestImpl($configSetter, $configured, /* expected: */ $configured);
    }

    /**
     * @dataProvider configSetterTestDataProvider
     *
     * @param AgentConfigSetterBase $configSetter
     */
    public function testInvalidServiceVersionTooLong(AgentConfigSetterBase $configSetter): void
    {
        $expected = self::generateDummyMaxKeywordString();
        $this->serviceVersionConfigTestImpl($configSetter, /* configured: */ $expected . '_tail', $expected);
    }
}
