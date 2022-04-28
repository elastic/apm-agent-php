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

use Elastic\Apm\Impl\Log\LoggableToString;
use Elastic\Apm\Impl\Tracer;
use ElasticApmTests\Util\DataFromAgentExpectations;
use ElasticApmTests\Util\DataValidatorBase;
use ElasticApmTests\Util\ErrorDataExpectations;
use ElasticApmTests\Util\MetadataExpectations;
use ElasticApmTests\Util\MetadataValidator;
use ElasticApmTests\Util\MetricSetDataExpectations;
use ElasticApmTests\Util\TestCaseBase;
use ElasticApmTests\Util\TraceDataExpectations;
use ElasticApmTests\Util\TransactionDataExpectations;
use PHPUnit\Framework\TestCase;

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
        $this->fillPidToMetadataExpectations($transactionExpectations);
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
        $sampleRate = $agentConfig->effectiveTransactionSampleRateAsString();
        return $sampleRate === '0' ? false : ($sampleRate === '1' ? true : null);
    }

    private function fillErrorExpectations(TransactionDataExpectations $transactionExpectations): void
    {
        $this->error = new ErrorDataExpectations();
        TestCaseBase::assertGreaterThanZero(
            DataValidatorBase::setCommonProperties(/* src */ $transactionExpectations, /* dst */ $this->error)
        );
    }

    private function fillPidToMetadataExpectations(TransactionDataExpectations $transactionExpectations): void
    {
        $this->agentEphemeralIdToMetadata = [];
        foreach ($this->appCodeInvocation->appCodeHostsParams as $appCodeHostParams) {
            $metadataExpectations
                = self::buildMetadataExpectationsForHost($transactionExpectations, $appCodeHostParams);
            $metadataExpectations->agentEphemeralId = $appCodeHostParams->spawnedProcessId;
            $this->agentEphemeralIdToMetadata[$appCodeHostParams->spawnedProcessId] = $metadataExpectations;
        }
    }

    private static function buildMetadataExpectationsForHost(
        TransactionDataExpectations $transactionExpectations,
        AppCodeHostParams $appCodeHostParams
    ): MetadataExpectations {
        $metadata = new MetadataExpectations();
        TestCaseBase::assertGreaterThanZero(
            DataValidatorBase::setCommonProperties(/* src */ $transactionExpectations, /* dst */ $metadata)
        );

        $agentConfig = $appCodeHostParams->getEffectiveAgentConfig();
        $metadata->serviceName
            = MetadataValidator::deriveExpectedServiceName($agentConfig->serviceName());
        $metadata->serviceNodeConfiguredName
            = Tracer::limitNullableKeywordString($agentConfig->serviceNodeName());
        $metadata->serviceVersion
            = Tracer::limitNullableKeywordString($agentConfig->serviceVersion());
        $metadata->serviceEnvironment
            = Tracer::limitNullableKeywordString($agentConfig->environment());

        $metadata->configuredHostname = Tracer::limitNullableKeywordString($agentConfig->hostname());
        $metadata->detectedHostname
            = $metadata->configuredHostname === null ? self::detectHostname() : null;

        return $metadata;
    }

    public static function detectHostname(): ?string
    {
        $detected = gethostname();
        if ($detected === false) {
            return null;
        }

        return Tracer::limitKeywordString($detected);
    }

    private function fillMetricSetExpectations(TransactionDataExpectations $transactionExpectations): void
    {
        $this->metricSet = new MetricSetDataExpectations();
        TestCaseBase::assertGreaterThanZero(
            DataValidatorBase::setCommonProperties(/* src */ $transactionExpectations, /* dst */ $this->metricSet)
        );
    }

    private function fillTraceExpectations(TransactionDataExpectations $transactionExpectations): void
    {
        $this->trace = new TraceDataExpectations();
        $this->trace->transaction = $transactionExpectations;
        DataValidatorBase::setCommonProperties(/* src */ $transactionExpectations, /* dst */ $this->trace->span);
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
