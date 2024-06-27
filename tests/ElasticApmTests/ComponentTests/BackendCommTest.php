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
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Util\RangeUtil;
use ElasticApmTests\ComponentTests\Util\AppCodeRequestParams;
use ElasticApmTests\ComponentTests\Util\AppCodeTarget;
use ElasticApmTests\ComponentTests\Util\ComponentTestCaseBase;
use ElasticApmTests\ComponentTests\Util\DataFromAgentPlusRawAccumulator;
use ElasticApmTests\ComponentTests\Util\ExpectedEventCounts;
use ElasticApmTests\ComponentTests\Util\MockApmServer;
use ElasticApmTests\ComponentTests\Util\MockApmServerBehavior;
use ElasticApmTests\UnitTests\BackendCommBackoffUnitTest;
use ElasticApmTests\Util\AssertMessageStack;
use ElasticApmTests\Util\DataProviderForTestBuilder;
use ElasticApmTests\Util\MixedMap;
use ElasticApmTests\Util\TestCaseBase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @group smoke
 * @group does_not_require_external_services
 */
final class BackendCommTest extends ComponentTestCaseBase
{
    private const TRANSACTION_NAME_KEY = 'transaction_name';

    public static function appCodeForTestNumberOfConnections(MixedMap $appCodeArgs): void
    {
        $txName = $appCodeArgs->getString(self::TRANSACTION_NAME_KEY);
        ElasticApm::getCurrentTransaction()->setName($txName);
    }

    public function testNumberOfConnections(): void
    {
        if (self::skipIfMainAppCodeHostIsNotHttp()) {
            return;
        }

        self::runAndEscalateLogLevelOnFailure(
            self::buildDbgDescForTest(__CLASS__, __FUNCTION__),
            function (): void {
                $testCaseHandle = $this->getTestCaseHandle();
                $appCodeHost = $testCaseHandle->ensureMainAppCodeHost();
                $txNames = ['1st_test_TX', '2nd_test_TX'];
                foreach ($txNames as $txName) {
                    $appCodeHost->sendRequest(
                        AppCodeTarget::asRouted([__CLASS__, 'appCodeForTestNumberOfConnections']),
                        function (AppCodeRequestParams $appCodeRequestParams) use ($txName): void {
                            $appCodeRequestParams->setAppCodeArgs([self::TRANSACTION_NAME_KEY => $txName]);
                            $appCodeRequestParams->expectedTransactionName->setValue($txName);
                        }
                    );
                }
                $txCount = count($txNames);
                $dataFromAgent = $testCaseHandle->waitForDataFromAgent((new ExpectedEventCounts())->transactions($txCount));
                AssertMessageStack::newScope(/* out */ $dbgCtx, ['connections' => $dataFromAgent->getRaw()->getIntakeApiConnections()]);
                self::assertCount(1, $dataFromAgent->getRaw()->getIntakeApiConnections());
                $txIndex = 0;
                foreach ($dataFromAgent->idToTransaction as $tx) {
                    self::assertSame($txNames[$txIndex], $tx->name);
                    ++$txIndex;
                }
            }
        );
    }

    private const WAIT_FOR_RECONNECT_COUNT_KEY = 'wait_for_reconnect_count';

    /**
     * @return iterable<string, array{MixedMap}>
     */
    public function dataProviderForTestBackoff(): iterable
    {
        $waitForReconnectAfterErrorCountVariants = [2, 4];
        if (DataProviderForTestBuilder::isLongRunMode()) {
            $waitForReconnectAfterErrorCountVariants[] = 10;
        }

        $result = (new DataProviderForTestBuilder())
            ->addKeyedDimensionOnlyFirstValueCombinable(self::WAIT_FOR_RECONNECT_COUNT_KEY, $waitForReconnectAfterErrorCountVariants)
            ->build();

        return DataProviderForTestBuilder::convertEachDataSetToMixedMap(self::adaptKeyValueToSmoke($result));
    }

    public const IS_APM_SERVER_RETURNING_SUCCESS_KEY = 'is_apm_server_returning_success';

    public static function mockApmServerBehaviorForTestBackoff(MockApmServer $mockApmServer, MixedMap $args): MockApmServerBehavior
    {
        /**
         * @var class-string $anonymousClassNameForLogger
         * @noinspection PhpRedundantVariableDocTypeInspection
         */
        $anonymousClassNameForLogger = __CLASS__ . '_' . __FUNCTION__;
        $logger = BackendCommTest::getLoggerStatic(__NAMESPACE__, $anonymousClassNameForLogger, __FILE__);
        return new class ($mockApmServer, $args, $logger) extends MockApmServerBehavior {
            /** @var Logger */
            private $logger;

            /** @var bool */
            private $isApmServerReturningSuccess;

            /** @var int */
            private $callsCount = 0;

            public function __construct(MockApmServer $mockApmServer, MixedMap $args, Logger $logger)
            {
                parent::__construct($mockApmServer);

                $this->logger = $logger;

                $this->isApmServerReturningSuccess = $args->getBool(BackendCommTest::IS_APM_SERVER_RETURNING_SUCCESS_KEY);
            }

            /** @inheritDoc */
            public function processIntakeApiRequest(ServerRequestInterface $request): ResponseInterface
            {
                $respone = parent::processIntakeApiRequest($request);
                $newStatusCode = $respone->getStatusCode();

                /**
                 * If the HTTP response status code isn’t 2xx or if a request is prematurely closed (either on the TCP or HTTP level) the request MUST be considered failed.
                 *
                 * @see https://github.com/elastic/apm/blob/d8cb5607dbfffea819ab5efc9b0743044772fb23/specs/agents/transport.md#transport-errors
                 */
                $newStatusCodeFirstDigit = $this->isApmServerReturningSuccess ? intdiv($newStatusCode, 100) : (3 + $this->callsCount % 3);
                if ($this->isApmServerReturningSuccess) {
                    TestCaseBase::assertSame(2, $newStatusCodeFirstDigit);
                }
                $newStatusCode = $newStatusCodeFirstDigit * 100 + (($this->callsCount * 11) % 100);

                ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
                && $loggerProxy->log('', ['callsCount' => $this->callsCount, 'statusCode' => ['original' => $respone->getStatusCode(), 'new' => $newStatusCode]]);

                ++$this->callsCount;
                return $respone->withStatus($newStatusCode);
            }
        };
    }

    private const TRANSACTION_INDEX_KEY = 'transaction_index';

    public static function appCodeForTestBackoff(MixedMap $appCodeArgs): void
    {
        ElasticApm::getCurrentTransaction()->context()->setLabel(self::IS_APM_SERVER_RETURNING_SUCCESS_KEY, $appCodeArgs->getBool(self::IS_APM_SERVER_RETURNING_SUCCESS_KEY));
        ElasticApm::getCurrentTransaction()->context()->setLabel(self::TRANSACTION_INDEX_KEY, $appCodeArgs->getInt(self::TRANSACTION_INDEX_KEY));
    }

    /**
     * @dataProvider dataProviderForTestBackoff
     */
    public function testBackoff(MixedMap $testArgs): void
    {
        if (self::skipIfMainAppCodeHostIsNotHttp()) {
            return;
        }

        AssertMessageStack::newScope(/* out */ $dbgCtx, AssertMessageStack::funcArgs());

        $logger = self::getLoggerStatic(__NAMESPACE__, __CLASS__, __FILE__);
        $loggerProxyTrace = $logger->ifTraceLevelEnabledNoLine(__FUNCTION__);

        $loggerProxyTrace && $loggerProxyTrace->log(__LINE__, 'Entered', ['testArgs' => $testArgs]);

        $waitForReconnectAfterErrorCount = $testArgs->getInt(self::WAIT_FOR_RECONNECT_COUNT_KEY);

        $testCaseHandle = $this->getTestCaseHandle();
        $appCodeHost = $testCaseHandle->ensureMainAppCodeHost();

        $dataFromAgentAccumulator = new DataFromAgentPlusRawAccumulator();

        // In the first stage of the test we make mock APM Server return failure
        // to the agent's requests to Intake API.
        // We continue sending requests to app code until agent makes at least $waitForReconnectAfterErrorCount connections

        $testCaseHandle->setMockApmServerBehavior([__CLASS__, 'mockApmServerBehaviorForTestBackoff'], [self::IS_APM_SERVER_RETURNING_SUCCESS_KEY => false]);

        $microsecondsToSleepAfterRequestToAppCode = 1000 * 1000 /* = 1s */;
        $nextTransactionIndex = 0;

        while (true) {
            $appCodeHost->sendRequest(
                AppCodeTarget::asRouted([__CLASS__, 'appCodeForTestBackoff']),
                function (AppCodeRequestParams $appCodeRequestParams) use ($nextTransactionIndex): void {
                    $appCodeRequestParams->setAppCodeArgs([self::IS_APM_SERVER_RETURNING_SUCCESS_KEY => false, self::TRANSACTION_INDEX_KEY => $nextTransactionIndex]);
                }
            );
            ++$nextTransactionIndex;
            usleep($microsecondsToSleepAfterRequestToAppCode);
            $dataFromAgentAccumulator->addReceiverEvents($testCaseHandle->fetchNewDataFromMockApmServer(/* shouldWait */ false));
            $dataFromAgent = $dataFromAgentAccumulator->getAccumulatedData();
            $connections = $dataFromAgent->getRaw()->getIntakeApiConnections();
            if (count($connections) >= $waitForReconnectAfterErrorCount) {
                break;
            }
            $loggerProxyTrace && $loggerProxyTrace->log(__LINE__, 'Finished iteration', ['requests count' => $nextTransactionIndex, 'connections count' => count($connections)]);
        }
        $dbgCtx->add(['dataFromAgent' => &$dataFromAgent, 'connections' => &$connections]);

        $dbgCtx->pushSubScope();
        foreach (RangeUtil::generateFromToIncluding(1, count($connections) - 1) as $currentConnectionIndex) {
            $dbgCtx->add(['currentConnectionIndex' => $currentConnectionIndex]);
            $prevConnection = $connections[$currentConnectionIndex - 1];
            $currentConnection = $connections[$currentConnectionIndex];
            $expectedWaitTimeWithoutJitter = BackendCommBackoffUnitTest::expectedWaitTimeWithoutJitter(/* errorCount */ $currentConnectionIndex);
            $expectedJitter = ($expectedWaitTimeWithoutJitter >= 10) ? intval(floor($expectedWaitTimeWithoutJitter * 0.1)) : 0;
            $expectedMinWaitTime = $expectedWaitTimeWithoutJitter - $expectedJitter;
            self::assertGreaterThanOrEqual($expectedMinWaitTime, $currentConnection->timestampMonotonic - $prevConnection->timestampMonotonic);

            // After error from APM Server agent should not reuse the connection so each connection should have exactly one request
            self::assertCount(1, $prevConnection->getIntakeApiRequests());
            self::assertCount(1, $currentConnection->getIntakeApiRequests());
        }
        $dbgCtx->popSubScope();

        // In the second stage of the test we revert mock APM Server to normal behavior.
        // We expect that after some delay agent should start sending data without any gaps.

        $testCaseHandle->setMockApmServerBehavior([__CLASS__, 'mockApmServerBehaviorForTestBackoff'], [self::IS_APM_SERVER_RETURNING_SUCCESS_KEY => true]);
        $nextTransactionIndexAfterSwitch = $nextTransactionIndex;

        while (true) {
            $appCodeHost->sendRequest(
                AppCodeTarget::asRouted([__CLASS__, 'appCodeForTestBackoff']),
                function (AppCodeRequestParams $appCodeRequestParams) use ($nextTransactionIndex): void {
                    $appCodeRequestParams->setAppCodeArgs([self::IS_APM_SERVER_RETURNING_SUCCESS_KEY => true, self::TRANSACTION_INDEX_KEY => $nextTransactionIndex]);
                }
            );
            ++$nextTransactionIndex;
            usleep($microsecondsToSleepAfterRequestToAppCode);
            $dataFromAgentAccumulator->addReceiverEvents($testCaseHandle->fetchNewDataFromMockApmServer(/* shouldWait */ false));
            $dataFromAgent = $dataFromAgentAccumulator->getAccumulatedData();
            $transactionsWithApmServerReturningSuccess = self::findExecutionSegmentsWithLabelValue($dataFromAgent->idToTransaction, self::IS_APM_SERVER_RETURNING_SUCCESS_KEY, true);
            // We wait until there is at least 10 transactions created after mock APM Server was reverted to normal behavior
            if (count($transactionsWithApmServerReturningSuccess) >= 10) {
                break;
            }
            $loggerProxyTrace && $loggerProxyTrace->log(
                __LINE__,
                'Finished iteration (after reverting mock APM Server to normal behavior)',
                [
                    'requests count (after revert)'                   => ($nextTransactionIndex - $nextTransactionIndexAfterSwitch),
                    'transactionsWithApmServerReturningSuccess count' => count($transactionsWithApmServerReturningSuccess),
                ]
            );
        }
        $dbgCtx->add(['transactionsWithApmServerReturningSuccess' => $transactionsWithApmServerReturningSuccess]);

        $dbgCtx->pushSubScope();
        foreach (RangeUtil::generateFromToIncluding(1, count($transactionsWithApmServerReturningSuccess) - 1) as $i) {
            $dbgCtx->add(['$i' => $i]);
            $prevTxIndex = self::getLabel($transactionsWithApmServerReturningSuccess[$i - 1], self::TRANSACTION_INDEX_KEY);
            self::assertIsInt($prevTxIndex);
            $currTxIndex = self::getLabel($transactionsWithApmServerReturningSuccess[$i], self::TRANSACTION_INDEX_KEY);
            self::assertIsInt($currTxIndex);
            // After reverting mock APM Server to normal behavior agent should drop any data so there should not be any gaps
            self::assertSame($prevTxIndex + 1, $currTxIndex);
        }
        $dbgCtx->popSubScope();
    }
}
