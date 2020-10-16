<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests;

use Elastic\Apm\ElasticApm;
use Elastic\Apm\Impl\Config\OptionNames;
use Elastic\Apm\Impl\Util\ArrayUtil;
use Elastic\Apm\Tests\ComponentTests\Util\ComponentTestCaseBase;
use Elastic\Apm\Tests\ComponentTests\Util\ConfigSetterBase;
use Elastic\Apm\Tests\ComponentTests\Util\DataFromAgent;
use Elastic\Apm\Tests\ComponentTests\Util\TestProperties;

final class VerifyServerCertTest extends ComponentTestCaseBase
{
    /**
     * @return iterable<array<ConfigSetterBase|bool|null>>
     */
    public function configTestDataProvider(): iterable
    {
        yield [null, null];

        foreach ($this->configSetterTestDataProvider() as $configSetter) {
            self::assertCount(1, $configSetter);
            foreach ([false, true] as $verifyServerCert) {
                yield [$configSetter[0], $verifyServerCert];
            }
        }
    }

    /**
     * @param array<string, mixed> $args
     */
    public static function appCodeForConfigTest(array $args): void
    {
        $tx = ElasticApm::getCurrentTransaction();
        $verifyServerCert = ArrayUtil::getValueIfKeyExistsElse('verifyServerCert', $args, null);
        $tx->setLabel('verifyServerCert', $verifyServerCert);
    }

    /**
     * @dataProvider configTestDataProvider
     *
     * @param ConfigSetterBase|null $configSetter
     * @param bool|null             $verifyServerCert
     */
    public function testConfig(?ConfigSetterBase $configSetter, ?bool $verifyServerCert): void
    {
        $testProperties = new TestProperties(
            [__CLASS__, 'appCodeForConfigTest'],
            /* appCodeArgs: */ ['verifyServerCert' => $verifyServerCert]
        );
        if (is_null($verifyServerCert)) {
            self::assertNull($configSetter);
        } else {
            self::assertNotNull($configSetter);
            $testProperties->withConfigSetter($configSetter)->setOption(
                OptionNames::VERIFY_SERVER_CERT,
                $verifyServerCert ? 'true' : 'false'
            );
        }
        $this->sendRequestToInstrumentedAppAndVerifyDataFromAgentEx(
            $testProperties,
            function (DataFromAgent $dataFromAgent) use ($verifyServerCert): void {
                $tx = $dataFromAgent->singleTransaction();
                self::assertCount(1, $tx->getLabels());
                self::assertSame($verifyServerCert, $tx->getLabels()['verifyServerCert']);
            }
        );
    }
}
