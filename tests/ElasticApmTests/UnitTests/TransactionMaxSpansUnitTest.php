<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\UnitTests;

use Elastic\Apm\ElasticApm;
use Elastic\Apm\Impl\Config\OptionNames;
use Elastic\Apm\Impl\GlobalTracerHolder;
use Elastic\Apm\Impl\TracerBuilder;
use Elastic\Apm\Tests\TestsSharedCode\TransactionMaxSpansTest\Args;
use Elastic\Apm\Tests\TestsSharedCode\TransactionMaxSpansTest\SharedCode;
use Elastic\Apm\Tests\UnitTests\Util\MockConfigRawSnapshotSource;
use Elastic\Apm\Tests\UnitTests\Util\UnitTestCaseBase;
use Elastic\Apm\Tests\Util\IterableUtilForTests;

class TransactionMaxSpansUnitTest extends UnitTestCaseBase
{
    private function variousCombinationsTestImpl(Args $testArgs): void
    {
        ///////////////////////////////
        // Arrange

        $this->setUpTestEnv(
            function (TracerBuilder $builder) use ($testArgs): void {
                $mockConfig = new MockConfigRawSnapshotSource();
                if (!$testArgs->isSampled) {
                    $mockConfig->set(OptionNames::TRANSACTION_SAMPLE_RATE, '0');
                }
                if (!is_null($testArgs->configTransactionMaxSpans)) {
                    $mockConfig->set(OptionNames::TRANSACTION_MAX_SPANS, strval($testArgs->configTransactionMaxSpans));
                }
                $builder->withConfigRawSnapshotSource($mockConfig);
                $this->mockEventSink->shouldValidateAgainstSchema = false;
            }
        );

        ///////////////////////////////
        // Act

        $tx = ElasticApm::beginCurrentTransaction('test_TX_name', 'test_TX_type');
        SharedCode::appCode($testArgs, $tx);
        $tx->end();

        ///////////////////////////////
        // Assert

        SharedCode::assertResults($testArgs, $this->mockEventSink->eventsFromAgent);
    }

    public function testVariousCombinations(): void
    {
        /**
         * @return iterable<Args>
         */
        $createTestArgsVariants = function (): iterable {
            // TODO: Sergey Kleyman: Find a way to run long time taking tests and change shouldLimitToBasic to false
            return SharedCode::testArgsVariants(/* shouldLimitToBasic: */ true);
        };

        /** @var ?int */
        $limitVariousCombinationsToVariantIndex = null;
        /** @var bool */
        $shouldPrintProgress = false;

        $variantIndex = 0;
        $testArgsVariantsCount = IterableUtilForTests::count($createTestArgsVariants());
        if (!is_null($limitVariousCombinationsToVariantIndex)) {
            $msg = "LIMITED to variant #$limitVariousCombinationsToVariantIndex out of $testArgsVariantsCount";
            fwrite(STDERR, PHP_EOL . __METHOD__ . ': ' . $msg . PHP_EOL);
        }

        /** @var Args $testArgs */
        foreach ($createTestArgsVariants() as $testArgs) {
            $testArgs->variantIndex = ++$variantIndex;
            if (!is_null($limitVariousCombinationsToVariantIndex)) {
                if ($variantIndex !== $limitVariousCombinationsToVariantIndex) {
                    continue;
                }
            }

            if ($shouldPrintProgress) {
                $msg = "variant #$variantIndex out of $testArgsVariantsCount: $testArgs";
                fwrite(STDERR, PHP_EOL . __METHOD__ . ': ' . $msg . PHP_EOL);
            }

            GlobalTracerHolder::unset();
            $this->mockEventSink->clear();
            $this->variousCombinationsTestImpl($testArgs);
        }
    }
}
