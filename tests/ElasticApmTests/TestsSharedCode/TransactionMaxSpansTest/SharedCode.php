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
use IteratorIterator;
use PHPUnit\Framework\TestCase;

final class SharedCode
{
    use StaticClassTrait;

    /**
     * @param bool $shouldLimitToBasic
     *
     * @return iterable<int|null>
     */
    public static function configTransactionMaxSpansVariants(bool $shouldLimitToBasic): iterable
    {
        yield from [null, 0];

        if (!$shouldLimitToBasic) {
            yield from [10, 1000];
        }
    }

    /**
     * @param ?int $configTransactionMaxSpans
     * @param bool $shouldLimitToBasic
     *
     * @return iterable<int>
     */
    public static function numberOfSpansToCreateVariants(
        ?int $configTransactionMaxSpans,
        bool $shouldLimitToBasic
    ): iterable {
        /** @var Set<int> */
        $result = new Set();

        $addInterestingValues = function (int $maxSpans) use ($result, $shouldLimitToBasic) {
            $result->add($maxSpans);
            $result->add($maxSpans + 1);

            if (!$shouldLimitToBasic) {
                $result->add($maxSpans - 1);
                $result->add(2 * $maxSpans);
            }
        };

        $addInterestingValues($configTransactionMaxSpans ?? OptionDefaultValues::TRANSACTION_MAX_SPANS);

        if (!$shouldLimitToBasic) {
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
     * @param bool $shouldLimitToBasic
     *
     * @return iterable<int>
     */
    public static function maxFanOutVariants(int $numberOfSpansToCreateValues, bool $shouldLimitToBasic): iterable
    {
        /** @var Set<int> */
        $result = new Set();

        $result->add(3);

        if (!$shouldLimitToBasic) {
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
     * @param bool $shouldLimitToBasic
     *
     * @return iterable<int>
     */
    public static function maxDepthVariants(int $numberOfSpansToCreateValues, bool $shouldLimitToBasic): iterable
    {
        /** @var Set<int> */
        $result = new Set();

        $result->add(3);

        if (!$shouldLimitToBasic) {
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
     * @param bool $shouldLimitToBasic
     *
     * @return iterable<Args>
     */
    public static function testArgsVariants(bool $shouldLimitToBasic): iterable
    {
        foreach ([true, false] as $isSampled) {
            foreach (self::configTransactionMaxSpansVariants($shouldLimitToBasic) as $configTransactionMaxSpans) {
                $numSpansVars = self::numberOfSpansToCreateVariants($configTransactionMaxSpans, $shouldLimitToBasic);
                foreach ($numSpansVars as $numberOfSpansToCreate) {
                    foreach (self::maxFanOutVariants($numberOfSpansToCreate, $shouldLimitToBasic) as $maxFanOut) {
                        foreach (self::maxDepthVariants($numberOfSpansToCreate, $shouldLimitToBasic) as $maxDepth) {
                            foreach ([true, false] as $shouldUseOnlyCurrentCreateSpanApis) {
                                $result = new Args();
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
