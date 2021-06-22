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

namespace ElasticApmTests\ComponentTests;

use Elastic\Apm\Impl\Config\OptionNames;
use Elastic\Apm\Impl\Util\ArrayUtil;
use ElasticApmTests\ComponentTests\Util\AgentConfigSetter;
use ElasticApmTests\ComponentTests\Util\ComponentTestCaseBase;
use ElasticApmTests\ComponentTests\Util\DataFromAgent;
use ElasticApmTests\ComponentTests\Util\TestProperties;
use ElasticApmTests\TestsSharedCode\SamplingTestSharedCode;

final class SamplingComponentTest extends ComponentTestCaseBase
{
    /**
     * @return iterable<array{?AgentConfigSetter, ?float}>
     */
    public function rateConfigTestDataProvider(): iterable
    {
        foreach (SamplingTestSharedCode::rates() as $rate) {
            if (is_null($rate)) {
                yield [null, $rate];
                continue;
            }

            yield [$this->randomConfigSetter(), $rate];
        }
    }

    /**
     * @param array<string, mixed> $args
     */
    public static function appCodeForTwoNestedSpansTest(array $args): void
    {
        $transactionSampleRate = ArrayUtil::getValueIfKeyExistsElse('transactionSampleRate', $args, null);
        SamplingTestSharedCode::appCodeForTwoNestedSpansTest($transactionSampleRate ?? 1.0);
    }

    /**
     * @dataProvider rateConfigTestDataProvider
     *
     * @param AgentConfigSetter|null $configSetter
     * @param float|null             $transactionSampleRate
     */
    public function testTwoNestedSpans(?AgentConfigSetter $configSetter, ?float $transactionSampleRate): void
    {
        $testProperties = (new TestProperties())
            ->withRoutedAppCode([__CLASS__, 'appCodeForTwoNestedSpansTest'])
            ->withAppCodeArgs(['transactionSampleRate' => $transactionSampleRate]);
        if (is_null($transactionSampleRate)) {
            self::assertNull($configSetter);
        } else {
            self::assertNotNull($configSetter);
            $testProperties->withAgentConfig(
                $configSetter->set(OptionNames::TRANSACTION_SAMPLE_RATE, strval($transactionSampleRate))
            );
        }
        $this->sendRequestToInstrumentedAppAndVerifyDataFromAgent(
            $testProperties,
            function (DataFromAgent $dataFromAgent) use ($transactionSampleRate): void {
                SamplingTestSharedCode::assertResultsForTwoNestedSpansTest(
                    $transactionSampleRate,
                    $dataFromAgent->eventsFromAgent()
                );
            }
        );
    }
}
