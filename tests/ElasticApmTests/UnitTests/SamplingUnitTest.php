<?php

declare(strict_types=1);

namespace ElasticApmTests\UnitTests;

use Elastic\Apm\ElasticApm;
use Elastic\Apm\Impl\Config\OptionNames;
use Elastic\Apm\Impl\TracerBuilder;
use ElasticApmTests\TestsSharedCode\SamplingTestSharedCode;
use ElasticApmTests\UnitTests\Util\MockConfigRawSnapshotSource;
use ElasticApmTests\UnitTests\Util\UnitTestCaseBase;

class SamplingUnitTest extends UnitTestCaseBase
{
    /**
     * @return iterable<array{float|null}>
     */
    public function ratesDataProvider(): iterable
    {
        foreach (SamplingTestSharedCode::rates() as $rate) {
            yield [$rate];
        }
    }

    /**
     * @dataProvider ratesDataProvider
     *
     * @param float|null $transactionSampleRate
     */
    public function testTwoNestedSpans(?float $transactionSampleRate): void
    {
        // Arrange

        $this->setUpTestEnv(
            function (TracerBuilder $builder) use ($transactionSampleRate): void {
                $mockConfig = new MockConfigRawSnapshotSource();
                if (!is_null($transactionSampleRate)) {
                    $mockConfig->set(OptionNames::TRANSACTION_SAMPLE_RATE, strval($transactionSampleRate));
                }
                $builder->withConfigRawSnapshotSource($mockConfig);
            }
        );

        // Act

        $tx = ElasticApm::beginCurrentTransaction('test_TX_name', 'test_TX_type');
        SamplingTestSharedCode::appCodeForTwoNestedSpansTest($transactionSampleRate ?? 1.0);
        $tx->end();

        // Assert

        SamplingTestSharedCode::assertResultsForTwoNestedSpansTest(
            $transactionSampleRate,
            $this->mockEventSink->eventsFromAgent
        );
    }
}
