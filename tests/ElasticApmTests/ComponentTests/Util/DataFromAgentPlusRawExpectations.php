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

use Elastic\Apm\Impl\MetadataDiscoverer;
use Elastic\Apm\Impl\Tracer;
use ElasticApmTests\Util\DataFromAgentExpectations;
use ElasticApmTests\Util\DataValidator;
use ElasticApmTests\Util\ErrorDataExpectations;
use ElasticApmTests\Util\MetadataExpectations;
use ElasticApmTests\Util\MetadataValidator;
use ElasticApmTests\Util\MetricSetDataExpectations;
use ElasticApmTests\Util\TestCaseBase;
use ElasticApmTests\Util\TraceDataExpectations;
use ElasticApmTests\Util\TransactionDataExpectations;

final class DataFromAgentPlusRawExpectations extends DataFromAgentExpectations
{
    /** @var AppCodeInvocation */
    public $appCodeInvocation;

    /** @var float */
    public $timeReceivedLastIntakeApiRequest;

    public function __construct(AppCodeInvocation $appCodeInvocation, float $timeReceivedLastIntakeApiRequest)
    {
        $this->appCodeInvocation = $appCodeInvocation;
        $this->timeReceivedLastIntakeApiRequest = $timeReceivedLastIntakeApiRequest;
        $this->fillExpectations();
    }

    private function fillExpectations(): void
    {
        $transactionExpectations = new TransactionDataExpectations();
        $transactionExpectations->isSampled = $this->deriveIsSampledExpectation();
        $transactionExpectations->timestampBefore = $this->appCodeInvocation->timestampBefore;
        $transactionExpectations->timestampAfter = $this->timeReceivedLastIntakeApiRequest;
        if (!$this->appCodeInvocation->appCodeRequestParams->shouldAssumeNoDroppedSpans) {
            $transactionExpectations->droppedSpansCount = null;
        }

        $this->fillErrorExpectations($transactionExpectations);
        $this->fillMetadataExpectations($transactionExpectations);
        $this->fillMetricSetExpectations($transactionExpectations);
        $this->fillTraceExpectations($transactionExpectations);
    }

    private function deriveIsSampledExpectation(): ?bool
    {
        /** @var ?bool */
        $sampleRate = null;
        foreach ($this->appCodeInvocation->appCodeHostsParams as $appCodeHostParams) {
            $currentSampleRate = self::deriveIsSampledExpectationForAppCodeHost($appCodeHostParams);
            if ($sampleRate === null) {
                $sampleRate = $currentSampleRate;
                continue;
            }
            if ($sampleRate !== $currentSampleRate) {
                return null;
            }
        }
        return $sampleRate;
    }

    private static function deriveIsSampledExpectationForAppCodeHost(AppCodeHostParams $appCodeHostParams): ?bool
    {
        $agentConfig = $appCodeHostParams->getEffectiveAgentConfig();
        $sampleRate = $agentConfig->transactionSampleRate();
        return $sampleRate === 0.0 ? false : ($sampleRate === 1.0 ? true : null);
    }

    private function fillErrorExpectations(TransactionDataExpectations $transactionExpectations): void
    {
        $this->error = new ErrorDataExpectations();
        TestCaseBase::assertGreaterThanZero(
            DataValidator::setCommonProperties(/* src */ $transactionExpectations, /* dst */ $this->error)
        );
    }

    private function fillMetadataExpectations(TransactionDataExpectations $transactionExpectations): void
    {
        $this->agentEphemeralIdToMetadata = [];
        foreach ($this->appCodeInvocation->appCodeHostsParams as $appCodeHostParams) {
            $metadataExpectations
                = self::buildMetadataExpectationsForHost($transactionExpectations, $appCodeHostParams);
            $metadataExpectations->agentEphemeralId->setValue($appCodeHostParams->spawnedProcessInternalId);
            $this->agentEphemeralIdToMetadata[$appCodeHostParams->spawnedProcessInternalId] = $metadataExpectations;
        }
    }

    private static function buildMetadataExpectationsForHost(
        TransactionDataExpectations $transactionExpectations,
        AppCodeHostParams $appCodeHostParams
    ): MetadataExpectations {
        $metadata = new MetadataExpectations();
        TestCaseBase::assertGreaterThanZero(
            DataValidator::setCommonProperties(/* src */ $transactionExpectations, /* dst */ $metadata)
        );

        $agentConfig = $appCodeHostParams->getEffectiveAgentConfig();
        $metadata->serviceName->setValue(MetadataValidator::deriveExpectedServiceName($agentConfig->serviceName()));
        $expectedServiceNodeName = Tracer::limitNullableKeywordString($agentConfig->serviceNodeName());
        $metadata->serviceNodeConfiguredName->setValue($expectedServiceNodeName);
        $expectedServiceVersion = Tracer::limitNullableKeywordString($agentConfig->serviceVersion());
        $metadata->serviceVersion->setValue($expectedServiceVersion);
        $expectedServiceEnvironment = Tracer::limitNullableKeywordString($agentConfig->environment());
        $metadata->serviceEnvironment->setValue($expectedServiceEnvironment);

        $configuredHostname = Tracer::limitNullableKeywordString($agentConfig->hostname());
        $metadata->configuredHostname->setValue($configuredHostname);
        $metadata->detectedHostname->setValue(
            $configuredHostname === null ? MetadataDiscoverer::detectHostname() : null
        );

        return $metadata;
    }

    private function fillMetricSetExpectations(TransactionDataExpectations $transactionExpectations): void
    {
        $this->metricSet = new MetricSetDataExpectations();
        TestCaseBase::assertGreaterThanZero(
            DataValidator::setCommonProperties(/* src */ $transactionExpectations, /* dst */ $this->metricSet)
        );
    }

    private function fillTraceExpectations(TransactionDataExpectations $transactionExpectations): void
    {
        $this->trace = new TraceDataExpectations();
        $this->trace->transaction = $transactionExpectations;
        DataValidator::setCommonProperties(/* src */ $transactionExpectations, /* dst */ $this->trace->span);
        $this->trace->span->timestampAfter = $this->appCodeInvocation->timestampAfter;

        $appCodeRequestParams = $this->appCodeInvocation->appCodeRequestParams;
        $this->trace->shouldVerifyRootTransaction = $appCodeRequestParams->shouldVerifyRootTransaction;
        $this->trace->rootTransactionName = $appCodeRequestParams->expectedTransactionName->getValue();
        $this->trace->rootTransactionType = $appCodeRequestParams->expectedTransactionType->getValue();

        if ($appCodeRequestParams instanceof HttpAppCodeRequestParams) {
            $this->trace->isRootTransactionHttp = true;
            $this->trace->rootTransactionHttpRequestMethod = $appCodeRequestParams->httpRequestMethod;
            $this->trace->rootTransactionUrlParts = $appCodeRequestParams->urlParts;
        } else {
            $this->trace->isRootTransactionHttp = false;
        }
    }
}
