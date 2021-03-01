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
use Elastic\Apm\Impl\TracerBuilder;
use ElasticApmTests\TestsSharedCode\TransactionMaxSpansTest\Args;
use ElasticApmTests\TestsSharedCode\TransactionMaxSpansTest\SharedCode;
use ElasticApmTests\UnitTests\Util\MockConfigRawSnapshotSource;
use ElasticApmTests\UnitTests\Util\TracerUnitTestCaseBase;

class TransactionMaxSpansUnitTest extends TracerUnitTestCaseBase
{
    private const IS_FULL_TESTING_MODE = false;

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
        /** @var Args $testArgs */
        foreach (SharedCode::testArgsVariants(self::IS_FULL_TESTING_MODE) as $testArgs) {
            if (!SharedCode::testEachArgsVariantProlog(self::IS_FULL_TESTING_MODE, $testArgs)) {
                continue;
            }

            GlobalTracerHolder::unset();
            $this->mockEventSink->clear();
            $this->variousCombinationsTestImpl($testArgs);
        }
    }
}
