<?php

declare(strict_types=1);

namespace ElasticApmTests\ComponentTests;

use Elastic\Apm\Impl\Config\OptionNames;
use Elastic\Apm\Impl\Util\ArrayUtil;
use ElasticApmTests\ComponentTests\Util\AgentConfigSetter;
use ElasticApmTests\ComponentTests\Util\ComponentTestCaseBase;
use ElasticApmTests\ComponentTests\Util\DataFromAgent;
use ElasticApmTests\ComponentTests\Util\TestProperties;
use ElasticApmTests\TestsSharedCode\SamplingTestSharedCode;

final class SamplingComponentTest extends ComponentTestCaseBase
{
    /**
     * @return iterable<array{?AgentConfigSetter, ?float}>
     */
    public function rateConfigTestDataProvider(): iterable
    {
        foreach (SamplingTestSharedCode::rates() as $rate) {
            if (is_null($rate)) {
                yield [null, $rate];
                continue;
            }

            foreach ($this->configSetterTestDataProvider() as $arrayWithConfigSetter) {
                self::assertCount(1, $arrayWithConfigSetter);
                yield [$arrayWithConfigSetter[0], $rate];
            }
        }
    }

    /**
     * @param array<string, mixed> $args
     */
    public static function appCodeForTwoNestedSpansTest(array $args): void
    {
        $transactionSampleRate = ArrayUtil::getValueIfKeyExistsElse('transactionSampleRate', $args, null);
        SamplingTestSharedCode::appCodeForTwoNestedSpansTest($transactionSampleRate ?? 1.0);
    }

    /**
     * @dataProvider rateConfigTestDataProvider
     *
     * @param AgentConfigSetter|null $configSetter
     * @param float|null             $transactionSampleRate
     */
    public function testTwoNestedSpans(?AgentConfigSetter $configSetter, ?float $transactionSampleRate): void
    {
        $testProperties = (new TestProperties())
            ->withRoutedAppCode([__CLASS__, 'appCodeForTwoNestedSpansTest'])
            ->withAppArgs(['transactionSampleRate' => $transactionSampleRate]);
        if (is_null($transactionSampleRate)) {
            self::assertNull($configSetter);
        } else {
            self::assertNotNull($configSetter);
            $testProperties->withAgentConfig(
                $configSetter->set(OptionNames::TRANSACTION_SAMPLE_RATE, strval($transactionSampleRate))
            );
        }
        $this->sendRequestToInstrumentedAppAndVerifyDataFromAgent(
            $testProperties,
            function (DataFromAgent $dataFromAgent) use ($transactionSampleRate): void {
                SamplingTestSharedCode::assertResultsForTwoNestedSpansTest(
                    $transactionSampleRate,
                    $dataFromAgent->eventsFromAgent()
                );
            }
        );
    }
}
