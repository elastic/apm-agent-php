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

/** @noinspection PhpDocMissingThrowsInspection, PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace ElasticApmTests\ComponentTests;

use Elastic\Apm\ElasticApm;
use Elastic\Apm\Impl\Config\OptionDefaultValues;
use Elastic\Apm\Impl\Config\OptionNames;
use Elastic\Apm\Impl\Util\ArrayUtil;
use ElasticApmTests\ComponentTests\Util\AppCodeHostParams;
use ElasticApmTests\ComponentTests\Util\AppCodeRequestParams;
use ElasticApmTests\ComponentTests\Util\AppCodeTarget;
use ElasticApmTests\ComponentTests\Util\ComponentTestCaseBase;
use ElasticApmTests\ComponentTests\Util\ExpectedEventCounts;
use ElasticApmTests\TestsSharedCode\TransactionMaxSpansTest\Args;
use ElasticApmTests\TestsSharedCode\TransactionMaxSpansTest\SharedCode;
use ElasticApmTests\Util\TransactionExpectations;
use PHPUnit\Framework\TestCase;

/**
 * @group smoke
 * @group does_not_require_external_services
 */
final class TransactionMaxSpansComponentTest extends ComponentTestCaseBase
{
    public const TESTING_DEPTH = SharedCode::TESTING_DEPTH_0;

    /**
     * @return iterable<array<mixed>>
     * @phpstan-return iterable<array{Args}>
     */
    public function dataProviderForTestVariousCombinations(): iterable
    {
        /** @var Args $testArgs */
        foreach (self::adaptToSmoke(SharedCode::testArgsVariants(self::TESTING_DEPTH)) as $testArgs) {
            yield [$testArgs];
        }
    }

    /**
     * @param array<string, mixed> $args
     */
    public static function appCodeForTestVariousCombinations(array $args): void
    {
        $testArgsAsDecodedJson = ArrayUtil::getValueIfKeyExistsElse('testArgs', $args, null);
        TestCase::assertNotNull($testArgsAsDecodedJson);
        TestCase::assertIsArray($testArgsAsDecodedJson);
        $testArgs = new Args();
        $testArgs->deserializeFromDecodedJson($testArgsAsDecodedJson);
        SharedCode::appCode($testArgs, ElasticApm::getCurrentTransaction());
    }

    /**
     * @dataProvider dataProviderForTestVariousCombinations
     *
     * @param Args $testArgs
     */
    public function testVariousCombinations(Args $testArgs): void
    {
        TransactionExpectations::$defaultDroppedSpansCount = null;
        TransactionExpectations::$defaultIsSampled = null;

        if (!SharedCode::testEachArgsVariantProlog(self::TESTING_DEPTH, $testArgs)) {
            self::dummyAssert();
            return;
        }

        $testCaseHandle = $this->getTestCaseHandle();
        $appCodeHost = $testCaseHandle->ensureMainAppCodeHost(
            function (AppCodeHostParams $appCodeParams) use ($testArgs): void {
                if ($testArgs->configTransactionMaxSpans !== null) {
                    $appCodeParams->setAgentOption(
                        OptionNames::TRANSACTION_MAX_SPANS,
                        strval($testArgs->configTransactionMaxSpans)
                    );
                }
                if (!$testArgs->isSampled) {
                    $appCodeParams->setAgentOption(OptionNames::TRANSACTION_SAMPLE_RATE, '0');
                }
            }
        );
        $appCodeHost->sendRequest(
            AppCodeTarget::asRouted([__CLASS__, 'appCodeForTestVariousCombinations']),
            function (AppCodeRequestParams $appCodeRequestParams) use ($testArgs): void {
                $appCodeRequestParams->shouldAssumeNoDroppedSpans = false;
                $appCodeRequestParams->setAppCodeArgs(['testArgs' => $testArgs]);
            }
        );
        $transactionMaxSpans = $testArgs->configTransactionMaxSpans ?? OptionDefaultValues::TRANSACTION_MAX_SPANS;
        if ($transactionMaxSpans < 0) {
            $transactionMaxSpans = OptionDefaultValues::TRANSACTION_MAX_SPANS;
        }
        $expectedStartedSpansCount = min($testArgs->numberOfSpansToCreate, $transactionMaxSpans);
        $dataFromAgent = $testCaseHandle->waitForDataFromAgent(
            (new ExpectedEventCounts())->transactions(1)->spans($expectedStartedSpansCount)
        );
        SharedCode::assertResults($testArgs, $dataFromAgent);
    }
}
