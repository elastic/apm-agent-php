<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests;

use Elastic\Apm\Impl\Constants;
use Elastic\Apm\Impl\MetadataDiscoverer;
use Elastic\Apm\Tests\ComponentTests\Util\ComponentTestCaseBase;
use Elastic\Apm\Tests\ComponentTests\Util\ConfigSetterBase;
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
        ?ConfigSetterBase $configSetter,
        ?string $configured,
        ?string $expected
    ): void {
        $this->configTestImpl(
            $configSetter,
            $configured,
            function (ConfigSetterBase $configSetter, string $configured): void {
                $configSetter->environment($configured);
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
     * @param ConfigSetterBase $configSetter
     */
    public function testCustomEnvironment(ConfigSetterBase $configSetter): void
    {
        $configured = 'custom service environment 9.8 @CI#!?';
        $this->environmentConfigTestImpl($configSetter, $configured, /* expected: */ $configured);
    }

    /**
     * @dataProvider configSetterTestDataProvider
     *
     * @param ConfigSetterBase $configSetter
     */
    public function testInvalidEnvironmentTooLong(ConfigSetterBase $configSetter): void
    {
        $expected = self::generateDummyMaxKeywordString();
        $this->environmentConfigTestImpl($configSetter, /* configured: */ $expected . '_tail', $expected);
    }

    private function serviceNameConfigTestImpl(
        ?ConfigSetterBase $configSetter,
        ?string $configured,
        string $expected
    ): void {
        $this->configTestImpl(
            $configSetter,
            $configured,
            function (ConfigSetterBase $configSetter, string $configured): void {
                $configSetter->serviceName($configured);
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
     * @param ConfigSetterBase $configSetter
     */
    public function testCustomServiceName(ConfigSetterBase $configSetter): void
    {
        $configured = 'custom service name';
        $this->serviceNameConfigTestImpl($configSetter, $configured, /* expected: */ $configured);
    }

    /**
     * @dataProvider configSetterTestDataProvider
     *
     * @param ConfigSetterBase $configSetter
     */
    public function testInvalidServiceNameChars(ConfigSetterBase $configSetter): void
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
     * @param ConfigSetterBase $configSetter
     */
    public function testInvalidServiceNameTooLong(ConfigSetterBase $configSetter): void
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
        ?ConfigSetterBase $configSetter,
        ?string $configured,
        ?string $expected
    ): void {
        $this->configTestImpl(
            $configSetter,
            $configured,
            function (ConfigSetterBase $configSetter, string $configured): void {
                $configSetter->serviceVersion($configured);
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
     * @param ConfigSetterBase $configSetter
     */
    public function testCustomServiceVersion(ConfigSetterBase $configSetter): void
    {
        $configured = 'v1.5.4-alpha@CI#.!?.';
        $this->serviceVersionConfigTestImpl($configSetter, $configured, /* expected: */ $configured);
    }

    /**
     * @dataProvider configSetterTestDataProvider
     *
     * @param ConfigSetterBase $configSetter
     */
    public function testInvalidServiceVersionTooLong(ConfigSetterBase $configSetter): void
    {
        $expected = self::generateDummyMaxKeywordString();
        $this->serviceVersionConfigTestImpl($configSetter, /* configured: */ $expected . '_tail', $expected);
    }
}
