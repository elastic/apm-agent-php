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
use Elastic\Apm\Impl\GlobalTracerHolder;
use ElasticApmTests\TestsSharedCode\TransactionMaxSpansTest\Args;
use ElasticApmTests\TestsSharedCode\TransactionMaxSpansTest\SharedCode;
use ElasticApmTests\UnitTests\Util\TracerUnitTestCaseBase;
use ElasticApmTests\Util\TracerBuilderForTests;
use ElasticApmTests\Util\TransactionExpectations;

class TransactionMaxSpansUnitTest extends TracerUnitTestCaseBase
{
    public const TESTING_DEPTH = SharedCode::TESTING_DEPTH_1;

    private function variousCombinationsTestImpl(Args $testArgs): void
    {
        ///////////////////////////////
        // Arrange

        $this->setUpTestEnv(
            function (TracerBuilderForTests $builder) use ($testArgs): void {
                if (!$testArgs->isSampled) {
                    $builder->withConfig(OptionNames::TRANSACTION_SAMPLE_RATE, '0');
                }
                if ($testArgs->configTransactionMaxSpans !== null) {
                    $builder
                        ->withConfig(OptionNames::TRANSACTION_MAX_SPANS, strval($testArgs->configTransactionMaxSpans));
                }
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

        SharedCode::assertResults($testArgs, $this->mockEventSink->dataFromAgent);
    }

    public function testVariousCombinations(): void
    {
        TransactionExpectations::$defaultDroppedSpansCount = null;
        TransactionExpectations::$defaultIsSampled = null;
        /** @var Args $testArgs */
        foreach (SharedCode::testArgsVariants(self::TESTING_DEPTH) as $testArgs) {
            if (!SharedCode::testEachArgsVariantProlog(self::TESTING_DEPTH, $testArgs)) {
                continue;
            }

            GlobalTracerHolder::unsetValue();
            $this->mockEventSink->clear();
            $this->variousCombinationsTestImpl($testArgs);
        }
    }
}
