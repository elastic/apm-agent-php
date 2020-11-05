<?php

/** @noinspection PhpDocMissingThrowsInspection, PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace ElasticApmTests\ComponentTests;

use Elastic\Apm\ElasticApm;
use Elastic\Apm\Impl\Config\OptionNames;
use Elastic\Apm\Impl\Util\ArrayUtil;
use ElasticApmTests\ComponentTests\Util\AgentConfigSetter;
use ElasticApmTests\ComponentTests\Util\ComponentTestCaseBase;
use ElasticApmTests\ComponentTests\Util\DataFromAgent;
use ElasticApmTests\ComponentTests\Util\TestProperties;
use ElasticApmTests\TestsSharedCode\TransactionMaxSpansTest\Args;
use ElasticApmTests\TestsSharedCode\TransactionMaxSpansTest\SharedCode;
use PHPUnit\Framework\TestCase;

final class TransactionMaxSpansComponentTest extends ComponentTestCaseBase
{
    private const IS_FULL_TESTING_MODE = false;

    /**
     * @return iterable<array<mixed>>
     * @phpstan-return iterable<array{?AgentConfigSetter, Args}>
     */
    public function dataProviderForTestVariousCombinations(): iterable
    {
        /** @var Args $testArgs */
        foreach (SharedCode::testArgsVariants(self::IS_FULL_TESTING_MODE) as $testArgs) {
            $setsAnyConfig = false;
            if (!is_null($testArgs->configTransactionMaxSpans)) {
                $setsAnyConfig = true;
            }
            if (!$setsAnyConfig && !$testArgs->isSampled) {
                $setsAnyConfig = true;
            }

            if ($setsAnyConfig) {
                foreach ($this->configSetterTestDataProvider() as $arrayWithConfigSetter) {
                    self::assertCount(1, $arrayWithConfigSetter);
                    yield [$arrayWithConfigSetter[0], $testArgs];
                }
            } else {
                yield [null, $testArgs];
            }
        }
    }

    /**
     * @param array<string, mixed> $args
     */
    public static function appCodeForTestVariousCombinations(array $args): void
    {
        $testArgsAsDecodedJson = ArrayUtil::getValueIfKeyExistsElse('testArgs', $args, null);
        TestCase::assertNotNull($testArgsAsDecodedJson);
        TestCase::assertIsArray($testArgsAsDecodedJson);
        $testArgs = new Args();
        $testArgs->deserializeFrom($testArgsAsDecodedJson);
        SharedCode::appCode($testArgs, ElasticApm::getCurrentTransaction());
    }

    /**
     * @dataProvider dataProviderForTestVariousCombinations
     *
     * @param AgentConfigSetter|null $configSetter
     * @param Args                   $testArgs
     */
    public function testVariousCombinations(?AgentConfigSetter $configSetter, Args $testArgs): void
    {
        if (!SharedCode::testEachArgsVariantProlog(self::IS_FULL_TESTING_MODE, $testArgs)) {
            self::assertTrue(true);
            return;
        }

        $testProperties = (new TestProperties())
            ->withRoutedAppCode([__CLASS__, 'appCodeForTestVariousCombinations'])
            ->withAppArgs(['testArgs' => $testArgs]);

        if (is_null($configSetter)) {
            self::assertNull($testArgs->configTransactionMaxSpans);
            self::assertTrue($testArgs->isSampled);
        } else {
            if (!is_null($testArgs->configTransactionMaxSpans)) {
                $configSetter->set(OptionNames::TRANSACTION_MAX_SPANS, strval($testArgs->configTransactionMaxSpans));
            }
            if (!$testArgs->isSampled) {
                $configSetter->set(OptionNames::TRANSACTION_SAMPLE_RATE, '0');
            }
            $testProperties->withAgentConfig($configSetter);
        }

        $this->sendRequestToInstrumentedAppAndVerifyDataFromAgent(
            $testProperties,
            function (DataFromAgent $dataFromAgent) use ($testArgs): void {
                SharedCode::assertResults($testArgs, $dataFromAgent->eventsFromAgent());
            }
        );
    }
}
