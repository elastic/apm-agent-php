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

namespace ElasticApmTests\ComponentTests\Util;

use Elastic\Apm\ElasticApm;
use Elastic\Apm\Impl\Config\OptionNames;
use Elastic\Apm\Impl\Log\Level as LogLevel;
use Elastic\Apm\Impl\Log\LoggableToString;
use ElasticApmTests\Util\ArrayUtilForTests;
use ElasticApmTests\Util\DataProviderForTestBuilder;
use ElasticApmTests\Util\IterableUtilForTests;
use ElasticApmTests\Util\MixedMap;
use PHPUnit\Framework\AssertionFailedError;

/**
 * @group does_not_require_external_services
 */
final class ComponentTestsUtilTest extends ComponentTestCaseBase
{
    private const INITIAL_LOG_LEVELS_KEY = 'initial_log_levels';
    private const FAIL_ON_RERUN_COUNT_KEY = 'fail_on_rerun_count';
    private const SHOULD_FAIL_KEY = 'should_fail';
    private const TEST_SUCCEEDED_LABEL_KEY = 'test_succeeded_label';
    private const ESCALATED_RERUNS_MAX_COUNT_KEY = AllComponentTestsOptionsMetadata::ESCALATED_RERUNS_MAX_COUNT_OPTION_NAME;

    /**
     * @return iterable<array{MixedMap}>
     */
    public function dataProviderForTestRunAndEscalateLogLevelOnFailure(): iterable
    {
        $initialLogLevels = [LogLevel::INFO, LogLevel::TRACE, LogLevel::DEBUG];

        $result = (new DataProviderForTestBuilder())
            ->addKeyedDimensionOnlyFirstValueCombinable(self::LOG_LEVEL_FOR_PROD_CODE_KEY, $initialLogLevels)
            ->addKeyedDimensionOnlyFirstValueCombinable(self::LOG_LEVEL_FOR_TEST_CODE_KEY, $initialLogLevels)
            ->addKeyedDimensionOnlyFirstValueCombinable(self::FAIL_ON_RERUN_COUNT_KEY, [1, 2, 3])
            ->addBoolKeyedDimensionOnlyFirstValueCombinable(self::SHOULD_FAIL_KEY)
            ->addKeyedDimensionOnlyFirstValueCombinable(self::ESCALATED_RERUNS_MAX_COUNT_KEY, [2, 0])
            ->build();

        return self::adaptToSmoke(DataProviderForTestBuilder::convertEachDataSetToMixedMap($result));
    }

    private static function buildFailMessage(int $runCount): string
    {
        return 'Dummy failed; run count: ' . $runCount;
    }

    public static function appCodeForTestRunAndEscalateLogLevelOnFailure(MixedMap $appCodeArgs): void
    {
        $dbgCtx['appCodeArgs'] = $appCodeArgs;
        $expectedLogLevelForProdCode = $appCodeArgs->getInt(self::LOG_LEVEL_FOR_PROD_CODE_KEY);
        $tracer = self::getTracerFromAppCode();
        $dbgCtx['optionNameToParsedValueMap'] = $tracer->getConfig()->getOptionNameToParsedValueMap();
        $actualLogLevelForProdCode = $tracer->getConfig()->effectiveLogLevel();
        $dbgCtx['actualLogLevelForProdCode'] = $actualLogLevelForProdCode;
        $dbgCtxAsStr = LoggableToString::convert($dbgCtx);
        self::assertSame($expectedLogLevelForProdCode, $actualLogLevelForProdCode, $dbgCtxAsStr);

        $expectedLogLevelForTestCode = $appCodeArgs->getInt(self::LOG_LEVEL_FOR_TEST_CODE_KEY);
        $dbgCtx['actual logLevelForTestCode'] = AmbientContextForTests::testConfig()->logLevel;
        $dbgCtxAsStr = LoggableToString::convert($dbgCtx);
        self::assertSame($expectedLogLevelForTestCode, AmbientContextForTests::testConfig()->logLevel, $dbgCtxAsStr);

        ElasticApm::getCurrentTransaction()->context()->setLabel(self::TEST_SUCCEEDED_LABEL_KEY, true);
    }

    /**
     * @return array<string, ?string>
     */
    private static function unsetLogLevelRelatedEnvVars(): array
    {
        $envVars = EnvVarUtilForTests::getAll();
        $logLevelRelatedEnvVarsToRestore = [];
        foreach (ConfigUtilForTests::allAgentLogLevelRelatedOptionNames() as $optName) {
            $envVarName = ConfigUtilForTests::agentOptionNameToEnvVarName($optName);
            if (array_key_exists($envVarName, $envVars)) {
                $logLevelRelatedEnvVarsToRestore[$envVarName] = $envVars[$envVarName];
                EnvVarUtilForTests::unset($envVarName);
            } else {
                $logLevelRelatedEnvVarsToRestore[$envVarName] = null;
            }

            self::assertNull(EnvVarUtilForTests::get($envVarName));
        }
        return $logLevelRelatedEnvVarsToRestore;
    }

    /**
     * @dataProvider dataProviderForTestRunAndEscalateLogLevelOnFailure
     */
    public function testRunAndEscalateLogLevelOnFailure(MixedMap $testArgs): void
    {
        $logLevelRelatedEnvVarsToRestore = self::unsetLogLevelRelatedEnvVars();
        $prodCodeSyslogLevelEnvVarName = ConfigUtilForTests::agentOptionNameToEnvVarName(OptionNames::LOG_LEVEL_SYSLOG);
        $initialLogLevelForProdCode = $testArgs->getInt(self::LOG_LEVEL_FOR_PROD_CODE_KEY);
        $initialLogLevelForProdCodeAsName = LogLevel::intToName($initialLogLevelForProdCode);
        EnvVarUtilForTests::set($prodCodeSyslogLevelEnvVarName, $initialLogLevelForProdCodeAsName);

        $logLevelForTestCodeToRestore = AmbientContextForTests::testConfig()->logLevel;
        $initialLogLevelForTestCode = $testArgs->getInt(self::LOG_LEVEL_FOR_TEST_CODE_KEY);
        AmbientContextForTests::resetLogLevel($initialLogLevelForTestCode);

        $rerunsMaxCountoRestore = AmbientContextForTests::testConfig()->escalatedRerunsMaxCount;
        $rerunsMaxCount = $testArgs->getInt(self::ESCALATED_RERUNS_MAX_COUNT_KEY);
        AmbientContextForTests::resetEscalatedRerunsMaxCount($rerunsMaxCount);

        $initialLevels = [];
        foreach (self::LOG_LEVEL_FOR_CODE_KEYS as $levelTypeKey) {
            $initialLevels[$levelTypeKey] = $testArgs->getInt($levelTypeKey);
        }
        $testArgs[self::INITIAL_LOG_LEVELS_KEY] = $initialLevels;
        $expectedEscalatedLevelsSeqCount = IterableUtilForTests::count(
            self::generateLevelsForRunAndEscalateLogLevelOnFailure($initialLevels, $rerunsMaxCount)
        );
        if ($rerunsMaxCount === 0) {
            self::assertSame(0, $expectedEscalatedLevelsSeqCount);
        }
        $failOnRerunCountArg = $testArgs->getInt(self::FAIL_ON_RERUN_COUNT_KEY);
        $expectedFailOnRunCount
            = $failOnRerunCountArg <= $expectedEscalatedLevelsSeqCount ? ($failOnRerunCountArg + 1) : 1;
        $expectedMessage = self::buildFailMessage($expectedFailOnRunCount);
        $shouldFail = $testArgs->getBool(self::SHOULD_FAIL_KEY);

        $nextRunCount = 1;
        try {
            self::runAndEscalateLogLevelOnFailure(
                self::buildDbgDescForTestWithArtgs(__CLASS__, __FUNCTION__, $testArgs),
                function () use ($testArgs, &$nextRunCount): void {
                    $testArgs['currentRunCount'] = $nextRunCount++;
                    $this->implTestRunAndEscalateLogLevelOnFailure($testArgs);
                }
            );
            $runAndEscalateLogLevelOnFailureExitedNormally = true;
        } catch (AssertionFailedError $ex) {
            $runAndEscalateLogLevelOnFailureExitedNormally = false;
            self::assertStringContainsString($expectedMessage, $ex->getMessage());
        }
        self::assertSame(!$shouldFail, $runAndEscalateLogLevelOnFailureExitedNormally);

        self::assertSame($rerunsMaxCount, AmbientContextForTests::testConfig()->escalatedRerunsMaxCount);
        AmbientContextForTests::resetEscalatedRerunsMaxCount($rerunsMaxCountoRestore);

        self::assertSame($initialLogLevelForTestCode, AmbientContextForTests::testConfig()->logLevel);
        AmbientContextForTests::resetLogLevel($logLevelForTestCodeToRestore);

        self::assertSame($initialLogLevelForProdCodeAsName, EnvVarUtilForTests::get($prodCodeSyslogLevelEnvVarName));
        foreach ($logLevelRelatedEnvVarsToRestore as $envVarName => $envVarValue) {
            EnvVarUtilForTests::setOrUnset($envVarName, $envVarValue);
        }
    }

    private function implTestRunAndEscalateLogLevelOnFailure(MixedMap $testArgs): void
    {
        $currentRunCount = $testArgs->getInt('currentRunCount');
        self::assertGreaterThanOrEqual(1, $currentRunCount);
        $currentReRunCount = $currentRunCount === 1 ? 0 : ($currentRunCount - 1);
        $shouldFail = $testArgs->getBool(self::SHOULD_FAIL_KEY);
        $failOnRerunCountArg = $testArgs->getInt(self::FAIL_ON_RERUN_COUNT_KEY);
        /** @var array<string, int> $initialLevels */
        $initialLevels = $testArgs->getArray(self::INITIAL_LOG_LEVELS_KEY);
        $shouldCurrentRunFail = $shouldFail && ($currentRunCount === 1 || $currentReRunCount === $failOnRerunCountArg);
        if ($currentRunCount === 1) {
            $expectedLevels = $initialLevels;
        } else {
            $rerunsMaxCount = $testArgs->getInt(self::ESCALATED_RERUNS_MAX_COUNT_KEY);
            self::assertTrue(
                IterableUtilForTests::getNthValue(
                    self::generateLevelsForRunAndEscalateLogLevelOnFailure($initialLevels, $rerunsMaxCount),
                    $currentReRunCount - 1,
                    $expectedLevels /* <- out */
                )
            );
        }

        $testCaseHandle = $this->getTestCaseHandle();
        $appCodeHost = $testCaseHandle->ensureMainAppCodeHost();
        $appCodeHost->sendRequest(
            AppCodeTarget::asRouted([__CLASS__, 'appCodeForTestRunAndEscalateLogLevelOnFailure']),
            function (AppCodeRequestParams $appCodeRequestParams) use ($expectedLevels): void {
                $appCodeArgs = [];
                foreach (self::LOG_LEVEL_FOR_CODE_KEYS as $levelTypeKey) {
                    $appCodeArgs[$levelTypeKey] = $expectedLevels[$levelTypeKey];
                }
                $appCodeRequestParams->setAppCodeArgs($appCodeArgs);
            }
        );

        $dataFromAgent = $this->waitForOneEmptyTransaction($testCaseHandle);
        $tx = $dataFromAgent->singleTransaction();
        self::assertTrue(ArrayUtilForTests::getBoolFromMap(self::TEST_SUCCEEDED_LABEL_KEY, self::getLabels($tx)));

        if ($shouldCurrentRunFail) {
            self::fail(self::buildFailMessage($currentRunCount));
        }
    }
}
