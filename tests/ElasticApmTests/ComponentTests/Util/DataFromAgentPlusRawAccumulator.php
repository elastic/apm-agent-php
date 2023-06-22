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

use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableToString;
use Elastic\Apm\Impl\Log\LoggableTrait;
use Elastic\Apm\Impl\Log\Logger;
use ElasticApmTests\Util\ArrayUtilForTests;
use ElasticApmTests\Util\AssertMessageStack;
use ElasticApmTests\Util\DataFromAgent;
use ElasticApmTests\Util\Deserialization\SerializedEventSinkTrait;
use ElasticApmTests\Util\LogCategoryForTests;
use ElasticApmTests\Util\TestCaseBase;

final class DataFromAgentPlusRawAccumulator implements RawDataFromAgentReceiverEventVisitorInterface, LoggableInterface
{
    use LoggableTrait;
    use SerializedEventSinkTrait;

    /** @var IntakeApiConnection[] */
    private $closedIntakeApiConnections = [];

    /** @var ?RawDataFromAgentReceiverEventConnectionStarted */
    private $openIntakeApiConnection = null;

    /** @var IntakeApiRequest[] */
    private $openIntakeApiConnectionRequests = [];

    /** @var DataFromAgent */
    private $dataParsed;

    /** @var Logger */
    private $logger;

    public function __construct()
    {
        $this->logger = AmbientContextForTests::loggerFactory()->loggerForClass(LogCategoryForTests::TEST_UTIL, __NAMESPACE__, __CLASS__, __FILE__)->addContext('this', $this);

        $this->dataParsed = new DataFromAgent();
    }

    public function dbgCounts(): ExpectedEventCounts
    {
        return (new ExpectedEventCounts())
            ->errors(count($this->dataParsed->idToError))
            ->metricSets(count($this->dataParsed->metricSets))
            ->spans(count($this->dataParsed->idToSpan))
            ->transactions(count($this->dataParsed->idToTransaction));
    }

    /**
     * @param RawDataFromAgentReceiverEvent[] $receiverEvents
     *
     * @return void
     */
    public function addReceiverEvents(array $receiverEvents): void
    {
        foreach ($receiverEvents as $receiverEvent) {
            $receiverEvent->visit($this);
        }
    }

    public function visitConnectionStarted(RawDataFromAgentReceiverEventConnectionStarted $event): void
    {
        $this->addNewConnection($event);
    }

    public function visitRequest(RawDataFromAgentReceiverEventRequest $event): void
    {
        $this->addIntakeApiRequest($event->request);
    }

    private function addNewConnection(RawDataFromAgentReceiverEventConnectionStarted $event): void
    {
        if ($this->openIntakeApiConnection === null) {
            TestCaseBase::assertCount(0, $this->openIntakeApiConnectionRequests);
        } else {
            $this->closedIntakeApiConnections[] = new IntakeApiConnection($this->openIntakeApiConnection, $this->openIntakeApiConnectionRequests);
            $this->openIntakeApiConnectionRequests = [];
        }

        $this->openIntakeApiConnection = $event;
    }

    private function addIntakeApiRequest(IntakeApiRequest $intakeApiRequest): void
    {
        $this->openIntakeApiConnectionRequests[] = $intakeApiRequest;

        $newDataParsed = IntakeApiRequestDeserializer::deserialize($intakeApiRequest);
        TestCaseBase::assertCount(1, $newDataParsed->metadatas);
        $metadata = $newDataParsed->metadatas[0];
        TestCaseBase::assertNotNull($metadata->service->agent);
        $dbgCtx = ['intakeApiRequest' => $intakeApiRequest, '$metadata' => $metadata];
        TestCaseBase::assertNotNull($metadata->service->agent->ephemeralId, LoggableToString::convert($dbgCtx));
        $intakeApiRequest->agentEphemeralId = $metadata->service->agent->ephemeralId;
        $this->appendParsedData($newDataParsed, $this->dataParsed);
    }

    private static function appendParsedData(DataFromAgent $from, DataFromAgent $to): void
    {
        foreach (get_object_vars($from) as $propName => $propValue) {
            TestCaseBase::assertIsArray($propValue);
            TestCaseBase::assertIsArray($to->$propName);
            ArrayUtilForTests::append(/* from */ $propValue, /* to, ref */ $to->$propName);
        }
    }

    public function hasReachedEventCounts(ExpectedEventCounts $expectedEventCounts): bool
    {
        AssertMessageStack::newScope(/* out */ $dbgCtx, array_merge(['this' => $this], AssertMessageStack::funcArgs()));
        $dbgCtx->pushSubScope();
        foreach (ApmDataKind::all() as $apmDataKind) {
            $dbgCtx->clearCurrentSubScope(['apmDataKind' => $apmDataKind]);
            $actualCount = $this->dataParsed->getApmDataCountForKind($apmDataKind);
            $logCtx = ['$apmDataKind' => $apmDataKind, '$expectedEventCounts' => $expectedEventCounts, '$actualCount' => $actualCount];
            ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log('Checking if has reached expected event count...', $logCtx);
            $hasReachedEventCounts = $expectedEventCounts->hasReachedCountForKind($apmDataKind, $actualCount);
            ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log('Checked if has reached expected event count', array_merge(['$hasReachedEventCounts' => $hasReachedEventCounts], $logCtx));
            if (!$hasReachedEventCounts) {
                return false;
            }
        }
        $dbgCtx->popSubScope();
        return true;
    }

    public function getAccumulatedData(): DataFromAgentPlusRaw
    {
        $intakeApiConnections = $this->closedIntakeApiConnections;
        if ($this->openIntakeApiConnection !== null) {
            $intakeApiConnections[] = new IntakeApiConnection($this->openIntakeApiConnection, $this->openIntakeApiConnectionRequests);
        }
        $result = new DataFromAgentPlusRaw(new RawDataFromAgent($intakeApiConnections));
        self::appendParsedData($this->dataParsed, $result);
        return $result;
    }
}
