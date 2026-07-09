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
use ElasticApmTests\ComponentTests\Util\AmbientContextForTests;
use ElasticApmTests\ComponentTests\Util\AppCodeHostParams;
use ElasticApmTests\ComponentTests\Util\AppCodeRequestParams;
use ElasticApmTests\ComponentTests\Util\AppCodeTarget;
use ElasticApmTests\ComponentTests\Util\ComponentTestCaseBase;
use ElasticApmTests\Util\AssertMessageStack;
use ElasticApmTests\Util\DataProviderForTestBuilder;
use ElasticApmTests\Util\MixedMap;

/**
 * @group smoke
 * @group does_not_require_external_services
 */
final class AgentEnabledConfigComponentTest extends ComponentTestCaseBase
{
    public static function appCodeForTestAgentEnabledConfig(MixedMap $appCodeArgs): void
    {
        $agentEnabledExpected = $appCodeArgs->getBool(OptionNames::ENABLED);

        /**
         * elastic_apm_* functions are provided by the elastic_apm extension
         *
         * @noinspection PhpFullyQualifiedNameUsageInspection, PhpUndefinedFunctionInspection
         * @phpstan-ignore-next-line
         */
        $agentEnabledAsSeenByNativePart = \elastic_apm_is_enabled();
        self::assertSame($agentEnabledExpected, $agentEnabledAsSeenByNativePart);
    }

    /**
     * @return iterable<string, array{MixedMap}>
     */
    public static function dataProviderForTestAgentEnabledConfig(): iterable
    {
        $result = (new DataProviderForTestBuilder())
            ->addKeyedDimensionAllValuesCombinable(OptionNames::ENABLED, [null, true, false])
            ->build();

        return DataProviderForTestBuilder::convertEachDataSetToMixedMap($result);
    }

    /**
     * @dataProvider dataProviderForTestAgentEnabledConfig
     */
    public function testAgentEnabledConfig(MixedMap $testArgs): void
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, AssertMessageStack::funcArgs());

        $testCaseHandle = $this->getTestCaseHandle();
        $appCodeHost = $testCaseHandle->ensureMainAppCodeHost(
            function (AppCodeHostParams $appCodeParams) use ($testArgs): void {
                self::setConfigIfNotNull($testArgs, OptionNames::ENABLED, $appCodeParams);
            }
        );

        $agentEnabledTestArg = $testArgs->getNullableBool(OptionNames::ENABLED);
        $agentEnabledActual = $appCodeHost->appCodeHostParams->getEffectiveAgentConfig()->enabled();
        if (($agentEnabledExpected = $agentEnabledTestArg ?? AmbientContextForTests::testConfig()->agentEnabledConfigDefault) !== null) {
            self::assertSame($agentEnabledExpected, $agentEnabledActual);
        }

        $appCodeHost->sendRequest(
            AppCodeTarget::asRouted([__CLASS__, 'appCodeForTestAgentEnabledConfig']),
            function (AppCodeRequestParams $appCodeRequestParams) use ($agentEnabledActual): void {
                $appCodeRequestParams->setAppCodeArgs([OptionNames::ENABLED => $agentEnabledActual]);
            }
        );

        if ($agentEnabledActual) {
            $dataFromAgent = $this->waitForOneEmptyTransaction($testCaseHandle);
            self::assertCount(1, $dataFromAgent->idToTransaction);
            self::assertCount(1, $dataFromAgent->metadatas);
        } else {
            $logger = self::getLoggerStatic(__NAMESPACE__, __CLASS__, __FILE__);
            $waitTimeSeconds = 5;
            ($loggerProxy = $logger->ifInfoLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log('Sleeping ' . $waitTimeSeconds . ' seconds to give agent enough time to send data (which it should not since it is disabled)...');
            sleep($waitTimeSeconds);
            $dataFromAgent = $testCaseHandle->getDataFromAgentWithoutWaiting();
            self::assertCount(0, $dataFromAgent->idToTransaction);
            self::assertCount(0, $dataFromAgent->metadatas);
        }
        self::assertCount(0, $dataFromAgent->idToSpan);
        self::assertCount(0, $dataFromAgent->idToError);
    }
}
