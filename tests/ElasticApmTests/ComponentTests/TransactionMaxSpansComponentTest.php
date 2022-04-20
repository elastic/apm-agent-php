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
use Elastic\Apm\Impl\Config\OptionNames;
use Elastic\Apm\Impl\Util\ArrayUtil;
use ElasticApmTests\ComponentTests\Util\AgentConfigSetter;
use ElasticApmTests\ComponentTests\Util\ComponentTestCaseBase;
use ElasticApmTests\ComponentTests\Util\DataFromAgent;
use ElasticApmTests\ComponentTests\Util\TestProperties;
use ElasticApmTests\TestsSharedCode\TransactionMaxSpansTest\Args;
use ElasticApmTests\TestsSharedCode\TransactionMaxSpansTest\SharedCode;
use PHPUnit\Framework\TestCase;

final class TransactionMaxSpansComponentTest extends ComponentTestCaseBase
{
    public const TESTING_DEPTH = SharedCode::TESTING_DEPTH_0;

    /**
     * @return iterable<array<mixed>>
     * @phpstan-return iterable<array{?AgentConfigSetter, Args}>
     */
    public function dataProviderForTestVariousCombinations(): iterable
    {
        /** @var Args $testArgs */
        foreach (SharedCode::testArgsVariants(self::TESTING_DEPTH) as $testArgs) {
            $setsAnyConfig = false;
            if (!is_null($testArgs->configTransactionMaxSpans)) {
                $setsAnyConfig = true;
            }
            if (!$setsAnyConfig && !$testArgs->isSampled) {
                $setsAnyConfig = true;
            }

            if ($setsAnyConfig) {
                yield [$this->randomConfigSetter(), $testArgs];
            } else {
                yield [null, $testArgs];
            }
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
        $testArgs->deserializeFrom($testArgsAsDecodedJson);
        SharedCode::appCode($testArgs, ElasticApm::getCurrentTransaction());
    }

    /**
     * @dataProvider dataProviderForTestVariousCombinations
     *
     * @param AgentConfigSetter|null $configSetter
     * @param Args                   $testArgs
     */
    public function testVariousCombinations(?AgentConfigSetter $configSetter, Args $testArgs): void
    {
        if (!SharedCode::testEachArgsVariantProlog(self::TESTING_DEPTH, $testArgs)) {
            self::dummyAssert();
            return;
        }

        $testProperties = (new TestProperties())
            ->withRoutedAppCode([__CLASS__, 'appCodeForTestVariousCombinations'])
            ->withAppCodeArgs(['testArgs' => $testArgs]);

        if (is_null($configSetter)) {
            self::assertNull($testArgs->configTransactionMaxSpans);
            self::assertTrue($testArgs->isSampled);
        } else {
            if (!is_null($testArgs->configTransactionMaxSpans)) {
                $configSetter->set(OptionNames::TRANSACTION_MAX_SPANS, strval($testArgs->configTransactionMaxSpans));
            }
            if (!$testArgs->isSampled) {
                $configSetter->set(OptionNames::TRANSACTION_SAMPLE_RATE, '0');
            }
            $testProperties->withAgentConfig($configSetter);
        }

        $this->sendRequestToInstrumentedAppAndVerifyDataFromAgent(
            $testProperties,
            function (DataFromAgent $dataFromAgent) use ($testArgs): void {
                SharedCode::assertResults($testArgs, $dataFromAgent->parsed());
            }
        );
    }
}
