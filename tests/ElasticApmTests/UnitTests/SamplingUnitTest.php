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

namespace ElasticApmTests\UnitTests;

use Elastic\Apm\ElasticApm;
use Elastic\Apm\Impl\Config\OptionNames;
use ElasticApmTests\TestsSharedCode\SamplingTestSharedCode;
use ElasticApmTests\UnitTests\Util\TracerUnitTestCaseBase;
use ElasticApmTests\Util\TracerBuilderForTests;
use ElasticApmTests\Util\TransactionExpectations;

class SamplingUnitTest extends TracerUnitTestCaseBase
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

        TransactionExpectations::$defaultIsSampled = null;
        $this->setUpTestEnv(
            function (TracerBuilderForTests $builder) use ($transactionSampleRate): void {
                if ($transactionSampleRate !== null) {
                    $builder->withConfig(OptionNames::TRANSACTION_SAMPLE_RATE, strval($transactionSampleRate));
                }
            }
        );

        // Act

        $tx = ElasticApm::beginCurrentTransaction('test_TX_name', 'test_TX_type');
        SamplingTestSharedCode::appCodeForTwoNestedSpansTest($transactionSampleRate ?? 1.0);
        $tx->end();

        // Assert

        SamplingTestSharedCode::assertResultsForTwoNestedSpansTest(
            $transactionSampleRate,
            $this->mockEventSink->dataFromAgent
        );
    }
}
