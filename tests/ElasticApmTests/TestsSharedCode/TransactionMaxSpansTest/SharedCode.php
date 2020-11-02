<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\TestsSharedCode\TransactionMaxSpansTest;

use Ds\Set;
use Elastic\Apm\Impl\Config\OptionDefaultValues;
use Elastic\Apm\Impl\Util\StaticClassTrait;
use Elastic\Apm\SpanDataInterface;
use Elastic\Apm\Tests\TestsSharedCode\EventsFromAgent;
use Elastic\Apm\Tests\Util\IterableUtilForTests;
use Elastic\Apm\TransactionInterface;
use PHPUnit\Framework\TestCase;

final class SharedCode
{
    use StaticClassTrait;

    // TODO: Sergey Kleyman: REVERT $limitVariousCombinationsToVariantIndex back to null
    /** @var ?int */
    private static $limitVariousCombinationsToVariantIndex = 4;

    /** @var ?int */
    private static $testArgsVariantsCount = null;

    /** @var bool */
    private static $shouldPrintProgress = true;

    /**
     * @param bool $isFullTestingMode
     *
     * @return iterable<int|null>
     */
    public static function configTransactionMaxSpansVariants(bool $isFullTestingMode): iterable
    {
        yield from [null, 0];

        if ($isFullTestingMode) {
            yield from [10, 1000];
        }
    }

    /**
     * @param ?int $configTransactionMaxSpans
     * @param bool $isFullTestingMode
     *
     * @return iterable<int>
     */
    public static function numberOfSpansToCreateVariants(
        ?int $configTransactionMaxSpans,
        bool $isFullTestingMode
    ): iterable {
        /** @var Set<int> */
        $result = new Set();

        $addInterestingValues = function (int $maxSpans) use ($result, $isFullTestingMode) {
            $result->add($maxSpans);
            $result->add($maxSpans + 1);

            if ($isFullTestingMode) {
                $result->add($maxSpans - 1);
                $result->add(2 * $maxSpans);
            }
        };

        $addInterestingValues($configTransactionMaxSpans ?? OptionDefaultValues::TRANSACTION_MAX_SPANS);

        if ($isFullTestingMode) {
            $result->add(0);
        }

        foreach ($result as $value) {
            if ($value >= 0) {
                yield $value;
            }
        }
    }

    /**
     * @param int  $numberOfSpansToCreateValues
     * @param bool $isFullTestingMode
     *
     * @return iterable<int>
     */
    public static function maxFanOutVariants(int $numberOfSpansToCreateValues, bool $isFullTestingMode): iterable
    {
        /** @var Set<int> */
        $result = new Set();

        $result->add(3);

        if ($isFullTestingMode) {
            $result->add(1);
            $result->add($numberOfSpansToCreateValues);
        }

        foreach ($result as $value) {
            if ($value > 0) {
                yield $value;
            }
        }
    }

    /**
     * @param int  $numberOfSpansToCreateValues
     * @param bool $isFullTestingMode
     *
     * @return iterable<int>
     */
    public static function maxDepthVariants(int $numberOfSpansToCreateValues, bool $isFullTestingMode): iterable
    {
        /** @var Set<int> */
        $result = new Set();

        $result->add(3);

        if ($isFullTestingMode) {
            $result->add(1);
            $result->add(2);
            $result->add($numberOfSpansToCreateValues);
            $result->add($numberOfSpansToCreateValues - 1);
        }

        foreach ($result as $value) {
            if ($value > 0) {
                yield $value;
            }
        }
    }

    /**
     * @param bool $isFullTestingMode
     *
     * @return iterable<Args>
     */
    public static function testArgsVariants(bool $isFullTestingMode): iterable
    {
        /** @var ?int */
        $limitVariousCombinationsToVariantIndex = null;

        $variantIndex = 1;
        foreach ([true, false] as $isSampled) {
            foreach (self::configTransactionMaxSpansVariants($isFullTestingMode) as $configTransactionMaxSpans) {
                $numSpansVars = self::numberOfSpansToCreateVariants($configTransactionMaxSpans, $isFullTestingMode);
                foreach ($numSpansVars as $numberOfSpansToCreate) {
                    foreach (self::maxFanOutVariants($numberOfSpansToCreate, $isFullTestingMode) as $maxFanOut) {
                        foreach (self::maxDepthVariants($numberOfSpansToCreate, $isFullTestingMode) as $maxDepth) {
                            foreach ([true, false] as $shouldUseOnlyCurrentCreateSpanApis) {
                                $result = new Args();
                                $result->variantIndex = $variantIndex++;
                                $result->isSampled = $isSampled;
                                $result->configTransactionMaxSpans = $configTransactionMaxSpans;
                                $result->numberOfSpansToCreate = $numberOfSpansToCreate;
                                $result->maxFanOut = $maxFanOut;
                                $result->maxDepth = $maxDepth;
                                $result->shouldUseOnlyCurrentCreateSpanApis = $shouldUseOnlyCurrentCreateSpanApis;
                                yield $result;
                            }
                        }
                    }
                }
            }
        }
    }

    public static function testEachArgsVariantProlog(bool $isFullTestingMode, Args $testArgs): bool
    {
        self::$testArgsVariantsCount = IterableUtilForTests::count(SharedCode::testArgsVariants($isFullTestingMode));
        if ($testArgs->variantIndex === 1) {
            if (!is_null(self::$limitVariousCombinationsToVariantIndex)) {
                $msg = 'LIMITED to variant #'
                       . self::$limitVariousCombinationsToVariantIndex . ' out of '
                       . self::$testArgsVariantsCount;
                fwrite(STDERR, PHP_EOL . __METHOD__ . ': ' . $msg . PHP_EOL);
            }
        }

        $isThisVariantEnabled = is_null(self::$limitVariousCombinationsToVariantIndex)
                                || ($testArgs->variantIndex === self::$limitVariousCombinationsToVariantIndex);

        $shouldPrintProgress = is_null(self::$limitVariousCombinationsToVariantIndex)
            ? self::$shouldPrintProgress
            : $isThisVariantEnabled;
        if ($shouldPrintProgress) {
            $msg = 'variant #' . $testArgs->variantIndex . ' out of ' . self::$testArgsVariantsCount . ': ' . $testArgs;
            fwrite(STDERR, PHP_EOL . __METHOD__ . ': ' . $msg . PHP_EOL);
        }

        return $isThisVariantEnabled;
    }

    public static function appCode(Args $testArgs, TransactionInterface $tx): void
    {
        AppCode::run($testArgs, $tx);
    }

    public static function assertResults(Args $testArgs, EventsFromAgent $eventsFromAgent): void
    {
        $tx = $eventsFromAgent->singleTransaction();
        TestCase::assertSame($testArgs->isSampled, $tx->isSampled());

        $transactionMaxSpans = $testArgs->configTransactionMaxSpans ?? OptionDefaultValues::TRANSACTION_MAX_SPANS;
        if ($transactionMaxSpans < 0) {
            $transactionMaxSpans = OptionDefaultValues::TRANSACTION_MAX_SPANS;
        }

        $msg = "testArgs: $testArgs. eventsFromAgent: $eventsFromAgent.";
        if (!$tx->isSampled()) {
            TestCase::assertSame(0, $tx->getStartedSpansCount(), $msg);
            TestCase::assertSame(0, $tx->getDroppedSpansCount(), $msg);
            TestCase::assertEmpty($eventsFromAgent->idToSpan, $msg);
            return;
        }

        $expectedStartedSpansCount = min($testArgs->numberOfSpansToCreate, $transactionMaxSpans);
        TestCase::assertSame($expectedStartedSpansCount, $tx->getStartedSpansCount(), $msg);
        $expectedDroppedSpansCount = $testArgs->numberOfSpansToCreate - $expectedStartedSpansCount;
        TestCase::assertSame($expectedDroppedSpansCount, $tx->getDroppedSpansCount(), $msg);
        TestCase::assertCount($expectedStartedSpansCount, $eventsFromAgent->idToSpan, $msg);

        /** @var ?SpanDataInterface $spanMissingChildren */
        $spanMissingChildren = null;

        /** @var SpanDataInterface $span */
        foreach ($eventsFromAgent->idToSpan as $span) {
            $msg2 = $msg . ' spanId: ' . $span->getId() . '.';
            TestCase::assertArrayHasKey(AppCode::NUMBER_OF_CHILD_SPANS_LABEL_KEY, $span->getLabels(), $msg2);
            $createdChildCount = $span->getLabels()[AppCode::NUMBER_OF_CHILD_SPANS_LABEL_KEY];
            $sentChildCount = IterableUtilForTests::count($eventsFromAgent->findChildSpans($span->getId()));
            if ($tx->getDroppedSpansCount() === 0) {
                TestCase::assertSame($createdChildCount, $sentChildCount, $msg2);
            } else {
                TestCase::assertLessThanOrEqual($createdChildCount, $sentChildCount, $msg2);
            }
        }
    }
}
