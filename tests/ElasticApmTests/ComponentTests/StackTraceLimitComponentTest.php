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

use Elastic\Apm\ElasticApm;
use Elastic\Apm\Impl\Config\OptionDefaultValues;
use Elastic\Apm\Impl\Config\OptionNames;
use ElasticApmTests\ComponentTests\Util\AppCodeHostParams;
use ElasticApmTests\ComponentTests\Util\AppCodeRequestParams;
use ElasticApmTests\ComponentTests\Util\AppCodeTarget;
use ElasticApmTests\ComponentTests\Util\ComponentTestCaseBase;
use ElasticApmTests\ComponentTests\Util\ExpectedEventCounts;
use ElasticApmTests\TestsSharedCode\StackTraceLimitTestSharedCode;
use ElasticApmTests\Util\AssertMessageStack;
use ElasticApmTests\Util\MixedMap;

/**
 * @group does_not_require_external_services
 */
final class StackTraceLimitComponentTest extends ComponentTestCaseBase
{
    /**
     * @return iterable<string, array{MixedMap}>
     */
    public static function dataProviderForTestVariousConfigValues(): iterable
    {
        return StackTraceLimitTestSharedCode::dataProviderForTestVariousConfigValues();
    }

    public static function appCodeForTestVariousConfigValues(MixedMap $appCodeArgs): void
    {
        ElasticApm::getCurrentTransaction()->context()->setLabel(OptionNames::STACK_TRACE_LIMIT, self::getTracerFromAppCode()->getConfig()->stackTraceLimit());
        $expectedMaxNumberOfFrames = $appCodeArgs->getNullablePositiveOrZeroInt(StackTraceLimitTestSharedCode::EXPECTED_MAX_NUMBER_OF_FRAMES_KEY);
        StackTraceLimitTestSharedCode::implTestVariousConfigValuesActPart($expectedMaxNumberOfFrames);
    }

    /**
     * @dataProvider dataProviderForTestVariousConfigValues
     */
    public function testVariousConfigValues(MixedMap $testArgs): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, AssertMessageStack::funcArgs());
        $expectedMaxNumberOfFrames = $testArgs->getNullablePositiveOrZeroInt(StackTraceLimitTestSharedCode::EXPECTED_MAX_NUMBER_OF_FRAMES_KEY);

        $testCaseHandle = $this->getTestCaseHandle();

        $appCodeHost = $testCaseHandle->ensureMainAppCodeHost(
            function (AppCodeHostParams $appCodeParams) use ($testArgs): void {
                if (($optVal = $testArgs->getNullableString(OptionNames::STACK_TRACE_LIMIT)) !== null) {
                    $appCodeParams->setAgentOption(OptionNames::STACK_TRACE_LIMIT, $optVal);
                }
            }
        );
        $appCodeHost->sendRequest(
            AppCodeTarget::asRouted([__CLASS__, 'appCodeForTestVariousConfigValues']),
            function (AppCodeRequestParams $appCodeRequestParams) use ($testArgs): void {
                $appCodeRequestParams->setAppCodeArgs($testArgs);
            }
        );

        $dataFromAgent = $testCaseHandle->waitForDataFromAgent(
            (new ExpectedEventCounts())->transactions(1)->spans(StackTraceLimitTestSharedCode::implTestVariousConfigValuesExpectedSpanCount($expectedMaxNumberOfFrames))
        );

        StackTraceLimitTestSharedCode::implTestVariousConfigValuesAssertPart($expectedMaxNumberOfFrames, $dataFromAgent);
    }
}
