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

use ElasticApmTests\ComponentTests\Util\AppCodeHostParams;
use ElasticApmTests\ComponentTests\Util\AppCodeRequestParams;
use ElasticApmTests\ComponentTests\Util\AppCodeTarget;
use ElasticApmTests\ComponentTests\Util\ComponentTestCaseBase;
use ElasticApmTests\ComponentTests\Util\ExpectedEventCounts;
use ElasticApmTests\TestsSharedCode\SpanCompressionSharedCode;
use ElasticApmTests\Util\ArrayUtilForTests;
use ElasticApmTests\Util\AssertMessageStack;
use ElasticApmTests\Util\DataProviderForTestBuilder;
use ElasticApmTests\Util\MixedMap;

/**
 * @group does_not_require_external_services
 */
final class SpanCompressionComponentTest extends ComponentTestCaseBase
{
    /**
     * @return iterable<string, array{MixedMap}>
     */
    public static function dataProviderForTestOneCompressedSequence(): iterable
    {
        /**
         * @return iterable<array<string, mixed>>
         */
        $removeMockClock = function (): iterable {
            $sharedCodeDataSets = SpanCompressionSharedCode::dataProviderForTestOneCompressedSequence();
            foreach ($sharedCodeDataSets as $dataSet) {
                /** @var MixedMap $testArgs */
                $testArgs = ArrayUtilForTests::getSingleValue($dataSet);
                $sharedCode = new SpanCompressionSharedCode($testArgs);
                if ($sharedCode->mockClock === null) {
                    yield $testArgs->cloneAsArray();
                }
            }
        };

        return DataProviderForTestBuilder::convertEachDataSetToMixedMap(DataProviderForTestBuilder::keyEachDataSetWithDbgDesc($removeMockClock));
    }

    public static function appCodeForTestOneCompressedSequence(MixedMap $appCodeArgs): void
    {
        (new SpanCompressionSharedCode($appCodeArgs))->implTestOneCompressedSequenceAct();
    }

    /**
     * @dataProvider dataProviderForTestOneCompressedSequence
     */
    public function testOneCompressedSequence(MixedMap $testArgs): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, AssertMessageStack::funcArgs());

        $sharedCode = new SpanCompressionSharedCode($testArgs);
        $testCaseHandle = $this->getTestCaseHandle();

        $appCodeHost = $testCaseHandle->ensureMainAppCodeHost(
            function (AppCodeHostParams $appCodeParams) use ($sharedCode): void {
                $appCodeParams->setAgentOptions($sharedCode->agentConfigOptions);
            }
        );
        $appCodeHost->sendRequest(
            AppCodeTarget::asRouted([__CLASS__, 'appCodeForTestOneCompressedSequence']),
            function (AppCodeRequestParams $appCodeRequestParams) use ($testArgs): void {
                $appCodeRequestParams->setAppCodeArgs($testArgs);
            }
        );

        $dataFromAgent = $testCaseHandle->waitForDataFromAgent(
            (new ExpectedEventCounts())->transactions(1)->spans(count($sharedCode->expectedSpansForTestOneCompressedSequence()))
        );

        $sharedCode->implTestOneCompressedSequenceAssert($dataFromAgent);
    }
}
