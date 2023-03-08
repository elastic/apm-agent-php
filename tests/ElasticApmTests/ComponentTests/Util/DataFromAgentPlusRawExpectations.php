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
use ElasticApmTests\Util\ErrorExpectations;
use ElasticApmTests\Util\MetadataExpectations;
use ElasticApmTests\Util\MetadataValidator;
use ElasticApmTests\Util\MetricSetExpectations;
use ElasticApmTests\Util\TestCaseBase;
use ElasticApmTests\Util\TraceExpectations;
use ElasticApmTests\Util\TransactionExpectations;
use PHPUnit\Framework\Assert;

final class DataFromAgentPlusRawExpectations extends DataFromAgentExpectations
{
    /** @var AppCodeInvocation[] */
    public $appCodeInvocations;

    /** @var float */
    public $timeAllDataReceivedAtApmServer;

    /**
     * @param AppCodeInvocation[] $appCodeInvocations
     * @param float               $timeAllDataReceivedAtApmServer
     */
    public function __construct(array $appCodeInvocations, float $timeAllDataReceivedAtApmServer)
    {
        $this->appCodeInvocations = $appCodeInvocations;
        $this->timeAllDataReceivedAtApmServer = $timeAllDataReceivedAtApmServer;
        $this->fillExpectationsFromParsedData();
    }

    private function fillExpectationsFromParsedData(): void
    {
        Assert::assertNotEmpty($this->appCodeInvocations);
        foreach ($this->appCodeInvocations as $appCodeInvocation) {
            $this->addExpectationsForAppCodeInvocation($appCodeInvocation);
        }
    }

    private function addExpectationsForAppCodeInvocation(AppCodeInvocation $appCodeInvocation): void
    {
        $transactionExpectations = new TransactionExpectations();
        $transactionExpectations->isSampled = self::deriveIsSampledExpectation($appCodeInvocation);
        $transactionExpectations->timestampBefore = $appCodeInvocation->timestampBefore;
        $transactionExpectations->timestampAfter = $this->timeAllDataReceivedAtApmServer;
        if (!$appCodeInvocation->appCodeRequestParams->shouldAssumeNoDroppedSpans) {
            $transactionExpectations->droppedSpansCount = null;
        }

        self::addErrorExpectations($transactionExpectations);
        self::addMetadataExpectations($appCodeInvocation, $transactionExpectations);
        self::addMetricSetExpectations($transactionExpectations);
        self::addTraceExpectations($appCodeInvocation, $transactionExpectations);
    }

    private static function deriveIsSampledExpectation(AppCodeInvocation $appCodeInvocation): ?bool
    {
        /** @var ?bool $sampleRate */
        $sampleRate = null;
        foreach ($appCodeInvocation->appCodeHostsParams as $appCodeHostParams) {
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

    private function addErrorExpectations(TransactionExpectations $transactionExpectations): void
    {
        $errorExpectations = new ErrorExpectations();
        TestCaseBase::assertGreaterThanZero(
            self::setCommonProperties(/* src */ $transactionExpectations, /* dst */ $errorExpectations)
        );
        $this->errors[] = $errorExpectations;
    }

    private function addMetadataExpectations(
        AppCodeInvocation $appCodeInvocation,
        TransactionExpectations $transactionExpectations
    ): void {
        foreach ($appCodeInvocation->appCodeHostsParams as $appCodeHostParams) {
            $metadataExpectations
                = self::buildMetadataExpectationsForHost($appCodeHostParams, $transactionExpectations);
            $metadataExpectations->agentEphemeralId->setValue($appCodeHostParams->spawnedProcessInternalId);
            $this->agentEphemeralIdToMetadata[$appCodeHostParams->spawnedProcessInternalId] = $metadataExpectations;
        }
    }

    private static function buildMetadataExpectationsForHost(
        AppCodeHostParams $appCodeHostParams,
        TransactionExpectations $transactionExpectations
    ): MetadataExpectations {
        $metadata = new MetadataExpectations();
        TestCaseBase::assertGreaterThanZero(
            self::setCommonProperties(/* src */ $transactionExpectations, /* dst */ $metadata)
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

    private function addMetricSetExpectations(TransactionExpectations $transactionExpectations): void
    {
        $metricSetExpectations = new MetricSetExpectations();
        TestCaseBase::assertGreaterThanZero(
            self::setCommonProperties(/* src */ $transactionExpectations, /* dst */ $metricSetExpectations)
        );
        $this->metricSets[] = $metricSetExpectations;
    }

    private function addTraceExpectations(
        AppCodeInvocation $appCodeInvocation,
        TransactionExpectations $transactionExpectations
    ): void {
        $traceExpectations = new TraceExpectations();
        $traceExpectations->transaction = $transactionExpectations;
        self::setCommonProperties(/* src */ $transactionExpectations, /* dst */ $traceExpectations->span);
        $traceExpectations->span->timestampAfter = $appCodeInvocation->timestampAfter;

        $appCodeRequestParams = $appCodeInvocation->appCodeRequestParams;
        $traceExpectations->shouldVerifyRootTransaction = $appCodeRequestParams->shouldVerifyRootTransaction;
        $traceExpectations->rootTransactionName = $appCodeRequestParams->expectedTransactionName->getValue();
        $traceExpectations->rootTransactionType = $appCodeRequestParams->expectedTransactionType->getValue();

        if ($appCodeRequestParams instanceof HttpAppCodeRequestParams) {
            $traceExpectations->isRootTransactionHttp = true;
            $traceExpectations->rootTransactionHttpRequestMethod = $appCodeRequestParams->httpRequestMethod;
            $traceExpectations->rootTransactionUrlParts = $appCodeRequestParams->urlParts;
        } else {
            $traceExpectations->isRootTransactionHttp = false;
        }
        $this->traces[] = $traceExpectations;
    }
}
