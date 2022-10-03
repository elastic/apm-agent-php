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

namespace ElasticApmTests\TestsSharedCode;

use Elastic\Apm\ElasticApm;
use Elastic\Apm\Impl\Log\LoggableToString;
use Elastic\Apm\Impl\Util\StaticClassTrait;
use ElasticApmTests\Util\DataFromAgent;
use ElasticApmTests\Util\TestCaseBase;
use PHPUnit\Framework\TestCase;

final class SamplingTestSharedCode
{
    use StaticClassTrait;

    /**
     * @return iterable<float|null>
     */
    public static function rates(): iterable
    {
        yield from [null, 0.0, 0.001, 0.01, 0.1, 0.3, 0.5, 0.9, 1.0];
    }

    public static function appCodeForTwoNestedSpansTest(float $transactionSampleRate): void
    {
        $tx = ElasticApm::getCurrentTransaction();
        $expectedIsSampled = $transactionSampleRate === 1.0 ? true : ($transactionSampleRate === 0.0 ? false : null);
        if ($expectedIsSampled !== null) {
            TestCase::assertSame(
                $expectedIsSampled,
                $tx->isSampled(),
                LoggableToString::convert(['transactionSampleRate' => $transactionSampleRate])
            );
        }

        $tx->context()->setLabel('TX_label_key', 123);
        $tx->captureCurrentSpan(
            'span1_name',
            'span1_type',
            function () use ($tx) {
                $tx->captureCurrentSpan(
                    'span11_name',
                    'span11_type',
                    function () {
                    }
                );
            }
        );
    }

    public static function assertResultsForTwoNestedSpansTest(
        ?float $inputTransactionSampleRate,
        DataFromAgent $eventsFromAgent
    ): void {
        $tx = $eventsFromAgent->singleTransaction();
        $transactionSampleRate = $inputTransactionSampleRate ?? 1.0;
        if ($transactionSampleRate === 1.0) {
            TestCase::assertTrue($tx->isSampled);
        } elseif ($transactionSampleRate === 0.0) {
            TestCase::assertFalse($tx->isSampled);
        }

        if ($tx->isSampled) {
            TestCase::assertCount(2, $eventsFromAgent->idToSpan);
            // Started and dropped spans should be counted only for sampled transactions
            TestCase::assertSame(2, $tx->startedSpansCount);

            TestCaseBase::assertLabelsCount(1, $tx);
            TestCase::assertSame(123, TestCaseBase::getLabel($tx, 'TX_label_key'));

            TestCaseBase::assertSame($transactionSampleRate, $tx->sampleRate);
            foreach ($eventsFromAgent->idToSpan as $span) {
                TestCaseBase::assertSame($transactionSampleRate, $span->sampleRate);
            }
        } else {
            /**
             * @link https://github.com/elastic/apm/blob/master/specs/agents/tracing-sampling.md#non-sampled-transactions
             * For non-sampled transactions set the transaction attributes sampled: false and sample_rate: 0,
             * and omit context.
             * No spans should be captured.
             */

            TestCase::assertEmpty($eventsFromAgent->idToSpan);
            // Started and dropped spans should be counted only for sampled transactions
            TestCase::assertSame(0, $tx->startedSpansCount);

            TestCase::assertNull($tx->context);

            TestCaseBase::assertSame(0.0, $tx->sampleRate);
        }
        TestCase::assertSame(0, $tx->droppedSpansCount);
    }
}
