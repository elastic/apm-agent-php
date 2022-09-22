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
use ElasticApmTests\Util\Deserialization\SerializedEventSinkTrait;
use ElasticApmTests\Util\LogCategoryForTests;
use PHPUnit\Framework\TestCase;

final class DataFromAgentPlusRawAccumulator implements LoggableInterface
{
    use LoggableTrait;
    use SerializedEventSinkTrait;

    /** @var DataFromAgentPlusRaw */
    private $result;

    /** @var Logger */
    private $logger;

    public function __construct()
    {
        $this->result = new DataFromAgentPlusRaw();
        $this->logger = AmbientContextForTests::loggerFactory()->loggerForClass(
            LogCategoryForTests::TEST_UTIL,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        )->addContext('this', $this);
    }

    public function dbgCounts(): ExpectedEventCounts
    {
        return (new ExpectedEventCounts())
            ->errors(count($this->result->idToError))
            ->metricSets(count($this->result->metricSets))
            ->spans(count($this->result->idToSpan))
            ->transactions(count($this->result->idToTransaction));
    }

    /**
     * @param IntakeApiRequest[] $intakeApiRequests
     *
     * @return void
     */
    public function addIntakeApiRequests(array $intakeApiRequests): void
    {
        foreach ($intakeApiRequests as $intakeApiRequest) {
            $dataFromAgent = IntakeApiRequestDeserializer::deserialize($intakeApiRequest);
            TestCase::assertCount(1, $dataFromAgent->metadatas);
            $metadata = $dataFromAgent->metadatas[0];
            TestCase::assertNotNull($metadata->service->agent);
            TestCase::assertNotNull(
                $metadata->service->agent->ephemeralId,
                LoggableToString::convert(
                    [
                        '$intakeApiRequests' => $intakeApiRequests,
                        '$metadata'          => $metadata,
                    ]
                )
            );
            $intakeApiRequest->agentEphemeralId = $metadata->service->agent->ephemeralId;
            $this->result->intakeApiRequests[] = $intakeApiRequest;
            foreach (get_object_vars($dataFromAgent) as $propName => $propValue) {
                TestCase::assertIsArray($propValue);
                TestCase::assertIsArray($this->result->$propName);
                $this->result->$propName = array_merge($this->result->$propName, $propValue);
            }
        }
    }

    public function hasReachedEventCounts(ExpectedEventCounts $expectedEventCounts): bool
    {
        foreach (ApmDataKind::all() as $apmDataKind) {
            $actualCount = $this->result->getApmDataCountForKind($apmDataKind);
            $hasReachedEventCounts = $expectedEventCounts->hasReachedCountForKind($apmDataKind, $actualCount);
            ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Checked if has reached expected event count',
                [
                    '$apmDataKind' => $apmDataKind,
                    '$hasReachedEventCounts' => $hasReachedEventCounts,
                    '$expectedEventCounts' => $expectedEventCounts,
                    '$actualCount' => $actualCount,
                    '$this' => $this,
                ]
            );
            if (!$hasReachedEventCounts) {
                return false;
            }
        }
        return true;
    }

    public function getAccumulatedData(): DataFromAgentPlusRaw
    {
        return $this->result;
    }
}
