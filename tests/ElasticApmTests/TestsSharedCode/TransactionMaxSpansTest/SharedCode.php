<?php

/*
 * Licensed to Elasticsearch B.V. under one or more contributor
 * license agreements. See the NOTICE file distributed with
 * this work for additional information regarding copyright
 * ownership. Elasticsearch B.V. licenses this file to you under
 * the Apache License, Version 2.0 (the "License"); you may
 * not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations
 * under the License.
 */

declare(strict_types=1);

namespace ElasticApmTests\TestsSharedCode\TransactionMaxSpansTest;

use Ds\Set;
use Elastic\Apm\Impl\Config\OptionDefaultValues;
use Elastic\Apm\Impl\Log\LoggableToString;
use Elastic\Apm\Impl\SpanData;
use Elastic\Apm\Impl\Util\StaticClassTrait;
use Elastic\Apm\TransactionInterface;
use ElasticApmTests\TestsSharedCode\EventsFromAgent;
use ElasticApmTests\Util\IterableUtilForTests;
use ElasticApmTests\Util\TestCaseBase;
use PHPUnit\Framework\TestCase;

final class SharedCode
{
    use StaticClassTrait;

    /** @var ?int */
    private const LIMIT_TO_VARIANT_INDEX = null;

    /** @var bool */
    private const SHOULD_PRINT_PROGRESS = true;

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
        $testArgsVariantsCount = IterableUtilForTests::count(SharedCode::testArgsVariants($isFullTestingMode));
        if ($testArgs->variantIndex === 1) {
            /** @phpstan-ignore-next-line */
            if (!is_null(self::LIMIT_TO_VARIANT_INDEX) && self::SHOULD_PRINT_PROGRESS) {
                $msg = 'LIMITED to variant #'
                       . self::LIMIT_TO_VARIANT_INDEX . ' out of '
                       . $testArgsVariantsCount;
                fwrite(STDERR, PHP_EOL . __METHOD__ . ': ' . $msg . PHP_EOL);
            }
        }

        /** @phpstan-ignore-next-line */
        $isThisVariantEnabled = is_null(self::LIMIT_TO_VARIANT_INDEX)
                                || ($testArgs->variantIndex === self::LIMIT_TO_VARIANT_INDEX);

        $shouldPrintProgress = is_null(self::LIMIT_TO_VARIANT_INDEX)
            ? self::SHOULD_PRINT_PROGRESS
            : $isThisVariantEnabled; // @phpstan-ignore-line

        /** @phpstan-ignore-next-line */
        if ($shouldPrintProgress) {
            $msg = 'variant #' . $testArgs->variantIndex . ' out of ' . $testArgsVariantsCount
                   . ': ' . LoggableToString::convert($testArgs);
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
        TestCase::assertSame($testArgs->isSampled, $tx->isSampled);

        $transactionMaxSpans = $testArgs->configTransactionMaxSpans ?? OptionDefaultValues::TRANSACTION_MAX_SPANS;
        if ($transactionMaxSpans < 0) {
            $transactionMaxSpans = OptionDefaultValues::TRANSACTION_MAX_SPANS;
        }

        $msg = LoggableToString::convert(['testArgs' => $testArgs, 'eventsFromAgent' => $eventsFromAgent]);
        if (!$tx->isSampled) {
            TestCase::assertSame(0, $tx->startedSpansCount, $msg);
            TestCase::assertSame(0, $tx->droppedSpansCount, $msg);
            TestCase::assertEmpty($eventsFromAgent->idToSpan, $msg);
            return;
        }

        $expectedStartedSpansCount = min($testArgs->numberOfSpansToCreate, $transactionMaxSpans);
        TestCase::assertSame($expectedStartedSpansCount, $tx->startedSpansCount, $msg);
        $expectedDroppedSpansCount = $testArgs->numberOfSpansToCreate - $expectedStartedSpansCount;
        TestCase::assertSame($expectedDroppedSpansCount, $tx->droppedSpansCount, $msg);
        TestCase::assertCount($expectedStartedSpansCount, $eventsFromAgent->idToSpan, $msg);

        /** @var ?SpanData $spanMissingChildren */
        $spanMissingChildren = null;

        /** @var SpanData $span */
        foreach ($eventsFromAgent->idToSpan as $span) {
            $msg2 = $msg . ' spanId: ' . $span->id . '.';
            TestCaseBase::assertHasLabel($span, AppCode::NUMBER_OF_CHILD_SPANS_LABEL_KEY, $msg2);
            $createdChildCount = TestCaseBase::getLabel($span, AppCode::NUMBER_OF_CHILD_SPANS_LABEL_KEY);
            $sentChildCount = IterableUtilForTests::count($eventsFromAgent->findChildSpans($span->id));
            if ($tx->droppedSpansCount === 0) {
                TestCase::assertSame($createdChildCount, $sentChildCount, $msg2);
            } else {
                TestCase::assertLessThanOrEqual($createdChildCount, $sentChildCount, $msg2);
            }
        }
    }
}
