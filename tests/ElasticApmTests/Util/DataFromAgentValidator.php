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

use Elastic\Apm\Impl\SpanData;
use Elastic\Apm\Impl\TransactionData;
use PHPUnit\Framework\TestCase;

class DataFromAgentValidator extends DataValidatorBase
{
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
        foreach ($this->actual->idToError as $error) {
            ErrorDataValidator::validate($error, $this->expectations->error);
        }

        foreach ($this->actual->metadatas as $metadata) {
            TestCase::assertNotNull($metadata->service->agent);
            $agentEphemeralId = $metadata->service->agent->ephemeralId;
            TestCase::assertNotNull($agentEphemeralId);
            MetadataValidator::validateNullableAgentEphemeralId($agentEphemeralId);
            TestCase::assertArrayHasKey($agentEphemeralId, $this->expectations->agentEphemeralIdToMetadata);
            MetadataValidator::validate($metadata, $this->expectations->agentEphemeralIdToMetadata[$agentEphemeralId]);
        }

        foreach ($this->actual->metricSets as $metricSet) {
            MetricSetDataValidator::validate($metricSet, $this->expectations->metricSet);
        }

        $this->validateTraces();
    }

    private function validateTraces(): void
    {
        /**
         * @template T of ExecutionSegmentData
         *
         * @param array<string, T> $srcArray
         *
         * @return array<string, array<string, T>>
         */
        $groupByTraceId = function (array $srcArray): array {
            $result = [];
            foreach ($srcArray as $srcArrayElement) {
                if (array_key_exists($srcArrayElement->traceId, $result)) {
                    $result[$srcArrayElement->traceId] = [];
                }
                $result[$srcArrayElement->traceId][$srcArrayElement->id] = $srcArrayElement;
            }
            return $result;
        };

        $transactionsByTraceId = $groupByTraceId($this->actual->idToTransaction);
        $spansByTraceId = $groupByTraceId($this->actual->idToSpan);
        TestCaseBase::assertListArrayIsSubsetOf(array_keys($spansByTraceId), array_keys($transactionsByTraceId));
        foreach ($transactionsByTraceId as $traceId => $idToTransaction) {
            TestCase::assertIsArray($idToTransaction);
            /** @var array<string, TransactionData> $idToTransaction */
            $idToSpan = array_key_exists($traceId, $spansByTraceId) ? $spansByTraceId[$traceId] : [];
            TestCase::assertIsArray($idToSpan);
            /** @var array<string, SpanData> $idToSpan */
            TraceDataValidator::validate(new TraceDataActual($idToTransaction, $idToSpan), $this->expectations->trace);
        }
    }
}
