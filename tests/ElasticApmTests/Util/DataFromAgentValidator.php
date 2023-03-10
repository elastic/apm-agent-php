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

namespace ElasticApmTests\Util;

use Elastic\Apm\Impl\Log\LoggableToString;
use Elastic\Apm\Impl\Util\ArrayUtil;
use Elastic\Apm\Impl\Util\RangeUtil;
use PHPUnit\Framework\Assert;

class DataFromAgentValidator
{
    use AssertValidTrait;

    /** @var DataFromAgentExpectations */
    protected $expectations;

    /** @var DataFromAgent */
    protected $actual;

    public static function validate(DataFromAgent $actual, DataFromAgentExpectations $expectations): void
    {
        (new self($expectations, $actual))->validateImpl();
    }

    private function __construct(DataFromAgentExpectations $expectations, DataFromAgent $actual)
    {
        $this->expectations = $expectations;
        $this->actual = $actual;
    }

    private function validateImpl(): void
    {
        $tracesInOrderReceived = $this->splitIntoTracesInOrderReceived();
        $traceIdsInOrderReceived = self::getTraceIdsInOrderReceived($tracesInOrderReceived);
        $this->validateTraces($tracesInOrderReceived);

        $this->validateErrors($traceIdsInOrderReceived);

        $this->validateMetadatas();

        $this->validateMetrics();
    }

    /**
     * @template T of \ElasticApmTests\Util\ExecutionSegmentDto
     *
     * @param array<string, T> $idToExecSegments
     *
     * @return array<string, array<string, T>>
     */
    private static function groupByTraceId(array $idToExecSegments): array
    {
        $result = [];
        foreach ($idToExecSegments as $execSegment) {
            $idToExecSegmentsForTraceId =& ArrayUtil::getOrAdd($execSegment->traceId, /* defaultValue */ [], $result);
            ArrayUtilForTests::addUnique($execSegment->id, $execSegment, /* ref */ $idToExecSegmentsForTraceId);
        }
        return $result;
    }

    /**
     * @return TraceActual[]
     */
    private function splitIntoTracesInOrderReceived(): array
    {
        $orderReceivedIndexToTrace = [];
        $transactionsInOrderReceived = array_values($this->actual->idToTransaction);
        $transactionsByTraceId = self::groupByTraceId($this->actual->idToTransaction);
        $spansByTraceId = self::groupByTraceId($this->actual->idToSpan);
        TestCaseBase::assertListArrayIsSubsetOf(array_keys($spansByTraceId), array_keys($transactionsByTraceId));
        foreach ($transactionsByTraceId as $traceId => $idToTransaction) {
            Assert::assertIsArray($idToTransaction);
            /** @var array<string, TransactionDto> $idToTransaction */
            $idToSpan = array_key_exists($traceId, $spansByTraceId) ? $spansByTraceId[$traceId] : [];
            Assert::assertIsArray($idToSpan);
            /** @var array<string, SpanDto> $idToSpan */

            $trace = new TraceActual($idToTransaction, $idToSpan);
            $orderReceivedIndex
                = array_search($trace->rootTransaction, $transactionsInOrderReceived, /* strict */ true);
            Assert::assertIsInt($orderReceivedIndex);
            ArrayUtilForTests::addUnique($orderReceivedIndex, $trace, /* ref */ $orderReceivedIndexToTrace);
        }
        ksort(/* ref*/ $orderReceivedIndexToTrace);
        return array_values($orderReceivedIndexToTrace);
    }

    /**
     * @param TraceActual[] $tracesInOrderReceived
     *
     * @return void
     */
    private function validateTraces(array $tracesInOrderReceived): void
    {
        $ctx = ['expectations->traces' => $this->expectations->traces];
        $ctx['tracesInOrderReceived'] = $tracesInOrderReceived;
        $ctxAsStr = LoggableToString::convert($ctx);
        Assert::assertSame(count($this->expectations->traces), count($tracesInOrderReceived), $ctxAsStr);
        foreach (RangeUtil::generateUpTo(count($tracesInOrderReceived)) as $indexOrderReceived) {
            $traceExpectations = $this->expectations->traces[$indexOrderReceived];
            TraceValidator::validate($tracesInOrderReceived[$indexOrderReceived], $traceExpectations);
        }
    }

    /**
     * @param TraceActual[] $tracesInOrderReceived
     *
     * @return string[]
     */
    private static function getTraceIdsInOrderReceived(array $tracesInOrderReceived): array
    {
        $result = [];
        foreach ($tracesInOrderReceived as $trace) {
            $result[] = $trace->rootTransaction->traceId;
        }
        return $result;
    }

    /**
     * @param string[] $traceIdsInOrderReceived
     *
     * @return void
     */
    private function validateErrors(array $traceIdsInOrderReceived): void
    {
        if (ArrayUtil::isEmpty($this->actual->idToError)) {
            return;
        }

        $orderTraceReceivedIndexToErrors = [];
        foreach ($this->actual->idToError as $error) {
            $orderTraceReceivedIndex = array_search($error->traceId, $traceIdsInOrderReceived, /* strict */ true);
            Assert::assertIsInt($orderTraceReceivedIndex);
            $errors =& ArrayUtil::getOrAdd(
                $orderTraceReceivedIndex,
                [] /* <- defaultValue */,
                $orderTraceReceivedIndexToErrors /* <- ref */
            );
            $errors[] = $error;
        }

        $msg = new AssertMessageBuilder(['expectations->errors' => $this->expectations->errors]);
        $msg->add('orderTraceReceivedIndexToErrors', $orderTraceReceivedIndexToErrors);
        Assert::assertSame(count($orderTraceReceivedIndexToErrors), count($this->expectations->errors), $msg->s());
        foreach (RangeUtil::generateUpTo(count($orderTraceReceivedIndexToErrors)) as $indexOrderReceived) {
            $errorExpectations = $this->expectations->errors[$indexOrderReceived];
            $errors = $orderTraceReceivedIndexToErrors[$indexOrderReceived];
            foreach ($errors as $error) {
                $error->assertMatches($errorExpectations);
            }
        }
    }

    private function validateMetadatas(): void
    {
        foreach ($this->actual->metadatas as $metadata) {
            Assert::assertNotNull($metadata->service->agent);
            $agentEphemeralId = $metadata->service->agent->ephemeralId;
            Assert::assertNotNull($agentEphemeralId);
            self::assertValidNullableKeywordString($agentEphemeralId);
            Assert::assertArrayHasKey($agentEphemeralId, $this->expectations->agentEphemeralIdToMetadata);
            MetadataValidator::assertValid(
                $metadata,
                $this->expectations->agentEphemeralIdToMetadata[$agentEphemeralId]
            );
        }
    }

    private function validateMetrics(): void
    {
        $firstExpectations = ArrayUtilForTests::getFirstValue($this->expectations->metricSets);
        $lastExpectations = ArrayUtilForTests::getLastValue($this->expectations->metricSets);
        $combinedExpectations = new MetricSetExpectations();
        $combinedExpectations->timestampBefore = $firstExpectations->timestampBefore;
        $combinedExpectations->timestampAfter = $lastExpectations->timestampAfter;
        foreach ($this->actual->metricSets as $metricSet) {
            MetricSetValidator::assertValid($metricSet, $combinedExpectations);
        }
    }
}
