<?php

declare(strict_types=1);

namespace ElasticApmTests\ComponentTests;

use Elastic\Apm\ElasticApm;
use Elastic\Apm\Impl\Config\OptionNames;
use Elastic\Apm\Impl\Util\ArrayUtil;
use ElasticApmTests\ComponentTests\Util\AgentConfigSetter;
use ElasticApmTests\ComponentTests\Util\ComponentTestCaseBase;
use ElasticApmTests\ComponentTests\Util\DataFromAgent;
use ElasticApmTests\ComponentTests\Util\TestProperties;

final class VerifyServerCertTest extends ComponentTestCaseBase
{
    /**
     * @return iterable<array<AgentConfigSetter|bool|null>>
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
     * @param AgentConfigSetter|null $configSetter
     * @param bool|null              $verifyServerCert
     */
    public function testConfig(?AgentConfigSetter $configSetter, ?bool $verifyServerCert): void
    {
        $testProperties = (new TestProperties())
            ->withRoutedAppCode([__CLASS__, 'appCodeForConfigTest'])
            ->withAppArgs(['verifyServerCert' => $verifyServerCert]);
        if (is_null($verifyServerCert)) {
            self::assertNull($configSetter);
        } else {
            self::assertNotNull($configSetter);
            $configSetter->set(OptionNames::VERIFY_SERVER_CERT, $verifyServerCert ? 'true' : 'false');
            $testProperties->withAgentConfig($configSetter);
        }
        $this->sendRequestToInstrumentedAppAndVerifyDataFromAgent(
            $testProperties,
            function (DataFromAgent $dataFromAgent) use ($verifyServerCert): void {
                $tx = $dataFromAgent->singleTransaction();
                self::assertCount(1, $tx->getLabels());
                self::assertSame($verifyServerCert, $tx->getLabels()['verifyServerCert']);
            }
        );
    }
}
