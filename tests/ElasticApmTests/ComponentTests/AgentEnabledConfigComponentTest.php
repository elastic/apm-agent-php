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
use ElasticApmTests\Util\AssertMessageStack;
use ElasticApmTests\Util\DataProviderForTestBuilder;
use ElasticApmTests\Util\MixedMap;

/**
 * @group smoke
 * @group does_not_require_external_services
 */
final class AgentEnabledConfigComponentTest extends ComponentTestCaseBase
{
    private const ENABLED_CONFIG_SEEN_BY_TEST = 'enabled_config_seen_by_test';

    public static function appCodeForTestWhenAgentIsDisabledItShouldNotSendAnyData(MixedMap $appCodeArgs): void
    {
        $enabledConfigSeenByTest = $appCodeArgs->getBool(self::ENABLED_CONFIG_SEEN_BY_TEST);

        /**
         * elastic_apm_* functions are provided by the elastic_apm extension
         *
         * @noinspection PhpFullyQualifiedNameUsageInspection, PhpUndefinedFunctionInspection
         * @phpstan-ignore-next-line
         */
        $enabledConfigSeenByNativePart = \elastic_apm_is_enabled();
        self::assertSame($enabledConfigSeenByTest, $enabledConfigSeenByNativePart);
    }

    /**
     * @return iterable<string, array{MixedMap}>
     */
    public static function dataProviderForTestWhenAgentIsDisabledItShouldNotSendAnyData(): iterable
    {
        $result = (new DataProviderForTestBuilder())
            ->addKeyedDimensionOnlyFirstValueCombinable(OptionNames::ENABLED, [null, true . false])
            ->build();

        return DataProviderForTestBuilder::convertEachDataSetToMixedMap($result);
    }

    /**
     * @dataProvider dataProviderForTestWhenAgentIsDisabledItShouldNotSendAnyData
     */
    public function testWhenAgentIsDisabledItShouldNotSendAnyData(MixedMap $testArgs): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, AssertMessageStack::funcArgs());

        $testCaseHandle = $this->getTestCaseHandle();
        $appCodeHost = $testCaseHandle->ensureMainAppCodeHost(
            function (AppCodeHostParams $appCodeParams) use ($testArgs): void {
                self::setConfigIfNotNull($testArgs, OptionNames::ENABLED, $appCodeParams);
            }
        );

        $enabledConfigSeenByTest = $appCodeHost->appCodeHostParams->getEffectiveAgentConfig()->enabled();
        $dbgCtx->add(compact('enabledConfigSeenByTest'));
        $logger = self::getLogger(__NAMESPACE__, __CLASS__, __FILE__);
        ($loggerProxy = $logger->ifInfoLevelEnabled(__LINE__, __FUNCTION__)) && $loggerProxy->log('', ['enabledConfigSeenByTest' => $enabledConfigSeenByTest]);

        $appCodeHost->sendRequest(
            AppCodeTarget::asRouted([__CLASS__, 'appCodeForTestWhenAgentIsDisabledItShouldNotSendAnyData']),
            function (AppCodeRequestParams $appCodeRequestParams) use ($enabledConfigSeenByTest): void {
                $appCodeRequestParams->setAppCodeArgs([self::ENABLED_CONFIG_SEEN_BY_TEST => $enabledConfigSeenByTest]);
            }
        );

        if ($enabledConfigSeenByTest) {
            $dataFromAgent = $this->waitForOneEmptyTransaction($testCaseHandle);
            self::assertCount(1, $dataFromAgent->idToTransaction);
            self::assertCount(1, $dataFromAgent->metadatas);
        } else {
            sleep(/* seconds */ 5);
            $dataFromAgent = $testCaseHandle->waitForDataFromAgent((new ExpectedEventCounts())->metadatas(0)->transactions(0), /* shouldValidate */ false);
            self::assertCount(0, $dataFromAgent->idToTransaction);
        }
        self::assertCount(0, $dataFromAgent->idToSpan);
        self::assertCount(0, $dataFromAgent->idToError);
    }
}
