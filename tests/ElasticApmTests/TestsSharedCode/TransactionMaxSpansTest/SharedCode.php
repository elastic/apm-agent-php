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
use Elastic\Apm\Impl\Util\StaticClassTrait;
use Elastic\Apm\TransactionInterface;
use ElasticApmTests\Util\AssertMessageStack;
use ElasticApmTests\Util\DataFromAgent;
use ElasticApmTests\Util\IterableUtilForTests;
use ElasticApmTests\Util\TestCaseBase;

final class SharedCode
{
    use StaticClassTrait;

    public const TESTING_DEPTH_0 = 0;
    public const TESTING_DEPTH_1 = 1;
    public const TESTING_DEPTH_MAX = 2;

    /**
     * @param int $testingDepth
     *
     * @return iterable<int|null>
     */
    public static function configTransactionMaxSpansVariants(int $testingDepth): iterable
    {
        // OLD TODO: Sergey Kleyman: UNCOMMENT and remove the "if" block below
        // yield null;
        if ($testingDepth >= self::TESTING_DEPTH_1) {
            yield null;
        }

        yield 0;

        if ($testingDepth >= self::TESTING_DEPTH_MAX) {
            yield from [10, 100, OptionDefaultValues::TRANSACTION_MAX_SPANS * 2];
        }
    }

    /**
     * @param ?int $configTransactionMaxSpans
     * @param int  $testingDepth
     *
     * @return iterable<int>
     */
    public static function numberOfSpansToCreateVariants(?int $configTransactionMaxSpans, int $testingDepth): iterable
    {
        /**
         * @var Set<int>
         * @noinspection PhpVarTagWithoutVariableNameInspection
         */
        $result = new Set();

        $addInterestingValues = function (int $maxSpans) use ($result, $testingDepth) {
            $result->add($maxSpans);
            $result->add($maxSpans + 1);

            if ($testingDepth >= self::TESTING_DEPTH_MAX) {
                $result->add($maxSpans - 1);
                $result->add(2 * $maxSpans);
            }
        };

        $addInterestingValues($configTransactionMaxSpans ?? OptionDefaultValues::TRANSACTION_MAX_SPANS);

        if ($testingDepth >= self::TESTING_DEPTH_MAX) {
            $result->add(0);
        }

        foreach ($result as $value) {
            if ($value >= 0) {
                yield $value;
            }
        }
    }

    /**
     * @param int $numberOfSpansToCreateValues
     * @param int $testingDepth
     *
     * @return iterable<int>
     */
    public static function maxFanOutVariants(int $numberOfSpansToCreateValues, int $testingDepth): iterable
    {
        /**
         * @var Set<int>
         * @noinspection PhpVarTagWithoutVariableNameInspection
         */
        $result = new Set();

        $result->add(3);

        if ($testingDepth >= self::TESTING_DEPTH_MAX) {
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
     * @param int $numberOfSpansToCreateValues
     * @param int $testingDepth
     *
     * @return iterable<int>
     */
    public static function maxDepthVariants(int $numberOfSpansToCreateValues, int $testingDepth): iterable
    {
        /**
         * @var Set<int>
         * @noinspection PhpVarTagWithoutVariableNameInspection
         */
        $result = new Set();

        $result->add(3);

        if ($testingDepth >= self::TESTING_DEPTH_MAX) {
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
     * @param int $testingDepth
     *
     * @return iterable<Args>
     */
    public static function testArgsVariants(int $testingDepth): iterable
    {
        // /** @var ?int */
        // $limitVariousCombinationsToVariantIndex = null;

        $variantIndex = 1;
        foreach ([true, false] as $isSampled) {
            foreach (self::configTransactionMaxSpansVariants($testingDepth) as $configTransactionMaxSpans) {
                $numSpansVars = self::numberOfSpansToCreateVariants($configTransactionMaxSpans, $testingDepth);
                foreach ($numSpansVars as $numberOfSpansToCreate) {
                    foreach (self::maxFanOutVariants($numberOfSpansToCreate, $testingDepth) as $maxFanOut) {
                        foreach (self::maxDepthVariants($numberOfSpansToCreate, $testingDepth) as $maxDepth) {
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

    public static function appCode(Args $testArgs, TransactionInterface $tx): void
    {
        AppCode::run($testArgs, $tx);
    }

    public static function assertResults(Args $testArgs, DataFromAgent $dataFromAgent): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, AssertMessageStack::funcArgs());

        $tx = $dataFromAgent->singleTransaction();
        TestCaseBase::assertSame($testArgs->isSampled, $tx->isSampled);

        $transactionMaxSpans = $testArgs->configTransactionMaxSpans ?? OptionDefaultValues::TRANSACTION_MAX_SPANS;
        if ($transactionMaxSpans < 0) {
            $transactionMaxSpans = OptionDefaultValues::TRANSACTION_MAX_SPANS;
        }

        if (!$tx->isSampled) {
            TestCaseBase::assertSame(0, $tx->startedSpansCount);
            TestCaseBase::assertSame(0, $tx->droppedSpansCount);
            TestCaseBase::assertEmpty($dataFromAgent->idToSpan);
            return;
        }

        $expectedStartedSpansCount = min($testArgs->numberOfSpansToCreate, $transactionMaxSpans);
        TestCaseBase::assertSame($expectedStartedSpansCount, $tx->startedSpansCount);
        $expectedDroppedSpansCount = $testArgs->numberOfSpansToCreate - $expectedStartedSpansCount;
        TestCaseBase::assertSame($expectedDroppedSpansCount, $tx->droppedSpansCount);
        TestCaseBase::assertCount($expectedStartedSpansCount, $dataFromAgent->idToSpan);

        $dbgCtx->pushSubScope();
        foreach ($dataFromAgent->idToSpan as $span) {
            $dbgCtx->clearCurrentSubScope(['span' => $span]);
            TestCaseBase::assertHasLabel($span, AppCode::NUMBER_OF_CHILD_SPANS_LABEL_KEY);
            $createdChildCount = TestCaseBase::getLabel($span, AppCode::NUMBER_OF_CHILD_SPANS_LABEL_KEY);
            TestCaseBase::assertIsInt($createdChildCount);
            $sentChildCount = IterableUtilForTests::count($dataFromAgent->findChildSpans($span->id));
            if ($tx->droppedSpansCount === 0) {
                TestCaseBase::assertSame($createdChildCount, $sentChildCount);
            } else {
                TestCaseBase::assertLessThanOrEqual($createdChildCount, $sentChildCount);
            }
        }
        $dbgCtx->popSubScope();
    }
}
