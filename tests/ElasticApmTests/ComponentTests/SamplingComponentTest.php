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
use ElasticApmTests\ComponentTests\Util\AppCodeHostParams;
use ElasticApmTests\ComponentTests\Util\AppCodeRequestParams;
use ElasticApmTests\ComponentTests\Util\AppCodeTarget;
use ElasticApmTests\ComponentTests\Util\ComponentTestCaseBase;
use ElasticApmTests\ComponentTests\Util\ExpectedEventCounts;
use ElasticApmTests\TestsSharedCode\SamplingTestSharedCode;
use ElasticApmTests\Util\TransactionExpectations;

/**
 * @group smoke
 * @group does_not_require_external_services
 */
final class SamplingComponentTest extends ComponentTestCaseBase
{
    private const TRANSACTION_SAMPLE_RATE_OPTION_VALUE_KEY = 'transactionSampleRateOptValue';

    /**
     * @return iterable<array{?float}>
     */
    public function rateConfigTestDataProvider(): iterable
    {
        foreach (self::adaptToSmoke(SamplingTestSharedCode::rates()) as $rate) {
            yield [$rate];
        }
    }

    /**
     * @param array<string, mixed> $args
     */
    public static function appCodeForTwoNestedSpansTest(array $args): void
    {
        $transactionSampleRate = self::getMandatoryAppCodeArg($args, self::TRANSACTION_SAMPLE_RATE_OPTION_VALUE_KEY);
        /** @var ?float $transactionSampleRate */
        SamplingTestSharedCode::appCodeForTwoNestedSpansTest($transactionSampleRate ?? 1.0);
    }

    /**
     * @dataProvider rateConfigTestDataProvider
     *
     * @param ?float $transactionSampleRateOptVal
     */
    public function testTwoNestedSpans(?float $transactionSampleRateOptVal): void
    {
        // Arrange

        TransactionExpectations::$defaultIsSampled = null;
        $testCaseHandle = $this->getTestCaseHandle();
        $appCodeHost = $testCaseHandle->ensureMainAppCodeHost(
            function (AppCodeHostParams $appCodeParams) use ($transactionSampleRateOptVal): void {
                if ($transactionSampleRateOptVal !== null) {
                    $appCodeParams->setAgentOption(
                        OptionNames::TRANSACTION_SAMPLE_RATE,
                        strval($transactionSampleRateOptVal)
                    );
                }
            }
        );

        // Act

        $appCodeHost->sendRequest(
            AppCodeTarget::asRouted([__CLASS__, 'appCodeForTwoNestedSpansTest']),
            function (AppCodeRequestParams $appCodeRequestParams) use ($transactionSampleRateOptVal): void {
                $appCodeRequestParams->setAppCodeArgs(
                    [self::TRANSACTION_SAMPLE_RATE_OPTION_VALUE_KEY => $transactionSampleRateOptVal]
                );
            }
        );
        $transactionSampleRate = $transactionSampleRateOptVal ?? 1.0;
        $minSpansCount = 0;
        $maxSpansCount = 2;
        if ($transactionSampleRate === 1.0) {
            $minSpansCount = $maxSpansCount;
        } elseif ($transactionSampleRate === 0.0) {
            $maxSpansCount = $minSpansCount;
        }
        if ($transactionSampleRate === 1.0) {
            TransactionExpectations::$defaultIsSampled = true;
        }
        $dataFromAgent = $testCaseHandle->waitForDataFromAgent(
            (new ExpectedEventCounts())->transactions(1)->spans($minSpansCount, $maxSpansCount)
        );

        // Assert

        SamplingTestSharedCode::assertResultsForTwoNestedSpansTest($transactionSampleRateOptVal, $dataFromAgent);
    }
}
