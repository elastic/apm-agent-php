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

namespace Elastic\Apm\Impl;

use Closure;
use Elastic\Apm\ExecutionSegmentInterface;
use Elastic\Apm\Impl\BreakdownMetrics\PerTransaction as BreakdownMetricsPerTransaction;
use Elastic\Apm\Impl\Config\DevInternalSubOptionNames;
use Elastic\Apm\Impl\Config\OptionNames;
use Elastic\Apm\Impl\Config\Snapshot as ConfigSnapshot;
use Elastic\Apm\Impl\Log\LogCategory;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Log\LogStreamInterface;
use Elastic\Apm\Impl\Util\IdGenerator;
use Elastic\Apm\Impl\Util\RandomUtil;
use Elastic\Apm\SpanInterface;
use Elastic\Apm\TransactionContextInterface;
use Elastic\Apm\TransactionInterface;
use Throwable;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class Transaction extends ExecutionSegment implements TransactionInterface
{
    /** @var Tracer */
    protected $tracer;

    /** @var TransactionData */
    private $data;

    /** @var ConfigSnapshot */
    private $config;

    /** @var ?Span */
    private $currentSpan = null;

    /** @var Logger */
    private $logger;

    /** @var SpanData[] */
    private $spansDataToSend = [];

    /** @var ErrorData[] */
    private $errorsDataToSend = [];

    /** @var TransactionContext|null */
    private $context = null;

    /** @var ?BreakdownMetricsPerTransaction */
    private $breakdownMetricsPerTransaction = null;

    /** @var ?string */
    private $outgoingTraceState;

    public function __construct(TransactionBuilder $builder)
    {
        $this->tracer = $builder->tracer;
        $this->data = new TransactionData();
        $this->config = $builder->tracer->getConfig();
        if ($this->config->breakdownMetrics()) {
            $this->breakdownMetricsPerTransaction = new BreakdownMetricsPerTransaction($this);
        }

        $distributedTracingData = self::extractDistributedTracingData($builder);
        if ($distributedTracingData === null) {
            $traceId = IdGenerator::generateId(Constants::TRACE_ID_SIZE_IN_BYTES);
            $sampleRate = $this->tracer->getConfig()->transactionSampleRate();
            $isSampled = self::makeSamplingDecision($sampleRate);
            /**
             * @link https://github.com/elastic/apm/blob/main/specs/agents/tracing-sampling.md#non-sampled-transactions
             * For non-sampled transactions set the transaction attributes sampled: false and sample_rate: 0
             */
            $sampleRateToMarkTransaction = $isSampled ? $sampleRate : 0.0;
            $this->outgoingTraceState
                = $this->tracer->httpDistributedTracing()->buildOutgoingTraceStateForRootTransaction($sampleRate);
        } else {
            $traceId = $distributedTracingData->traceId;
            $this->data->parentId = $distributedTracingData->parentId;
            $isSampled = $distributedTracingData->isSampled;
            $sampleRateToMarkTransaction = $distributedTracingData->sampleRate;
            $this->outgoingTraceState = $distributedTracingData->outgoingTraceState;
        }

        parent::__construct(
            $this->data,
            $builder->tracer,
            null /* <- parentExecutionSegment */,
            $traceId,
            $builder->name,
            $builder->type,
            $sampleRateToMarkTransaction,
            $builder->timestamp
        );

        $this->logger = $this->tracer->loggerFactory()
                                     ->loggerForClass(LogCategory::PUBLIC_API, __NAMESPACE__, __CLASS__, __FILE__)
                                     ->addContext('this', $this);

        $this->data->isSampled = $isSampled;

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Transaction created');
    }

    private static function extractDistributedTracingData(TransactionBuilder $builder): ?DistributedTracingDataInternal
    {
        /** @var string[] */
        $traceParentHeaderValues = [];
        /** @var string[] */
        $traceStateHeaderValues = [];
        self::extractDistributedTracingHeaders(
            $builder,
            $traceParentHeaderValues /* <- ref */,
            $traceStateHeaderValues /* <- ref */
        );
        return $builder->tracer->httpDistributedTracing()->parseHeaders(
            $traceParentHeaderValues,
            $traceStateHeaderValues
        );
    }


    /**
     * @param TransactionBuilder $builder
     * @param string[]           $traceParentHeaderValues
     * @param string[]           $traceStateHeaderValues
     */
    private static function extractDistributedTracingHeaders(
        TransactionBuilder $builder,
        array &$traceParentHeaderValues,
        array &$traceStateHeaderValues
    ): void {
        if ($builder->serializedDistTracingData !== null) {
            $traceParentHeaderValues[] = $builder->serializedDistTracingData;
            return;
        }

        $headersExtractor = $builder->headersExtractor;
        if ($headersExtractor === null) {
            return;
        }

        /**
         * @param null|string|string[] $headersExtractorRetVal
         *
         * @return string[]
         */
        $adaptHeadersExtractorRetVal = function ($headersExtractorRetVal): array {
            return $headersExtractorRetVal === null
                ? []
                : (is_string($headersExtractorRetVal) ? [$headersExtractorRetVal] : $headersExtractorRetVal);
        };

        $traceParentHeaderValues = $adaptHeadersExtractorRetVal(
            $headersExtractor(HttpDistributedTracing::TRACE_PARENT_HEADER_NAME)
        );
        $traceStateHeaderValues = $adaptHeadersExtractorRetVal(
            $headersExtractor(HttpDistributedTracing::TRACE_STATE_HEADER_NAME)
        );
    }

    /** @inheritDoc */
    public function getParentId(): ?string
    {
        return $this->data->parentId;
    }

    private static function makeSamplingDecision(float $sampleRate): bool
    {
        if ($sampleRate === 0.0) {
            return false;
        }
        if ($sampleRate === 1.0) {
            return true;
        }

        return RandomUtil::generate01Float() < $sampleRate;
    }

    public function tracer(): Tracer
    {
        return $this->tracer;
    }

    /** @inheritDoc */
    public function isSampled(): bool
    {
        return $this->data->isSampled;
    }

    /** @inheritDoc */
    public function containingTransaction(): Transaction
    {
        return $this;
    }

    /** @inheritDoc */
    public function parentExecutionSegment(): ?ExecutionSegment
    {
        return null;
    }

    /** @inheritDoc */
    public function context(): TransactionContextInterface
    {
        if (!$this->isSampled()) {
            return NoopTransactionContext::singletonInstance();
        }

        if ($this->context === null) {
            $this->data->context = new TransactionContextData();
            $this->context = new TransactionContext($this, $this->data->context);
        }

        return $this->context;
    }

    public function cloneContextData(): ?TransactionContextData
    {
        if ($this->data->context === null) {
            return null;
        }
        return clone $this->data->context;
    }

    /** @inheritDoc */
    public function setResult(?string $result): void
    {
        if ($this->beforeMutating()) {
            return;
        }

        $this->data->result = $this->tracer->limitNullableKeywordString($result);
    }

    /** @inheritDoc */
    public function getResult(): ?string
    {
        return $this->data->result;
    }

    /** @inheritDoc */
    public function getCurrentSpan(): SpanInterface
    {
        return $this->currentSpan ?? NoopSpan::singletonInstance();
    }

    public function setCurrentSpan(?Span $newCurrentSpan): void
    {
        $this->currentSpan = $newCurrentSpan;
    }

    public function getCurrentExecutionSegment(): ExecutionSegmentInterface
    {
        return $this->currentSpan ?? $this;
    }

    public function beginSpan(
        ExecutionSegment $parentExecutionSegment,
        string $name,
        string $type,
        ?string $subtype,
        ?string $action,
        ?float $timestamp
    ): ?Span {
        if ($this->beforeMutating() || !$this->tracer->isRecording()) {
            return null;
        }

        $isDropped = false;
        // Started and dropped spans should be counted only for sampled transactions
        if ($this->data->isSampled) {
            if ($this->data->startedSpansCount >= $this->config->transactionMaxSpans()) {
                $isDropped = true;
                ++$this->data->droppedSpansCount;
                if ($this->data->droppedSpansCount === 1) {
                    ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
                    && $loggerProxy->log(
                        'Starting to drop spans because of ' . OptionNames::TRANSACTION_MAX_SPANS . ' config',
                        [
                            'count($this->spansDataToSend)'                => count($this->spansDataToSend),
                            OptionNames::TRANSACTION_MAX_SPANS . ' config' => $this->config->transactionMaxSpans(),
                        ]
                    );
                }
            } else {
                ++$this->data->startedSpansCount;
            }
        }

        return new Span(
            $this->tracer,
            $this /* <- containingTransaction */,
            $parentExecutionSegment,
            $name,
            $type,
            $subtype,
            $action,
            $timestamp,
            $isDropped,
            $this->data->sampleRate
        );
    }

    /** @inheritDoc */
    public function beginChildSpan(
        string $name,
        string $type,
        ?string $subtype = null,
        ?string $action = null,
        ?float $timestamp = null
    ): SpanInterface {
        return
            $this->beginSpan(
                $this /* <- parentExecutionSegment */,
                $name,
                $type,
                $subtype,
                $action,
                $timestamp
            )
            ?? NoopSpan::singletonInstance();
    }

    /** @inheritDoc */
    public function captureChildSpan(
        string $name,
        string $type,
        Closure $callback,
        ?string $subtype = null,
        ?string $action = null,
        ?float $timestamp = null
    ) {
        /** @noinspection PhpUnhandledExceptionInspection */
        return $this->captureChildSpanImpl(
            $name,
            $type,
            $callback,
            $subtype,
            $action,
            $timestamp,
            1 /* numberOfStackFramesToSkip */
        );
    }

    /** @inheritDoc */
    public function beginCurrentSpan(
        string $name,
        string $type,
        ?string $subtype = null,
        ?string $action = null,
        ?float $timestamp = null
    ): SpanInterface {
        $this->currentSpan = $this->beginSpan(
            $this->currentSpan ?? $this /* <- parentExecutionSegment */,
            $name,
            $type,
            $subtype,
            $action,
            $timestamp
        );

        return $this->getCurrentSpan();
    }

    /** @inheritDoc */
    public function captureCurrentSpan(
        string $name,
        string $type,
        Closure $callback,
        ?string $subtype = null,
        ?string $action = null,
        ?float $timestamp = null
    ) {
        $newSpan = $this->beginCurrentSpan($name, $type, $subtype, $action, $timestamp);
        try {
            return $callback($newSpan);
        } catch (Throwable $throwable) {
            $newSpan->createErrorFromThrowable($throwable);
            throw $throwable;
        } finally {
            // Since endSpanEx was not called directly it should not be kept in the stack trace
            $newSpan->endSpanEx(/* numberOfStackFramesToSkip: */ 1);
        }
    }

    /** @inheritDoc */
    public function ensureParentId(): string
    {
        if ($this->data->parentId === null) {
            $this->data->parentId = IdGenerator::generateId(Constants::EXECUTION_SEGMENT_ID_SIZE_IN_BYTES);
            ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Setting parent ID for already existing transaction',
                ['parentId' => $this->data->parentId]
            );
        }

        return $this->data->parentId;
    }

    /** @inheritDoc */
    public function dispatchCreateError(ErrorExceptionData $errorExceptionData): ?string
    {
        if ($this->currentSpan === null) {
            return $this->tracer->doCreateError($errorExceptionData, /* transaction: */ $this, /* span */ null);
        }

        return $this->currentSpan->dispatchCreateError($errorExceptionData);
    }

    /** @inheritDoc */
    public function getDistributedTracingDataInternal(): ?DistributedTracingDataInternal
    {
        if ($this->currentSpan === null) {
            return $this->doGetDistributedTracingData(/* span */ null);
        }

        return $this->currentSpan->getDistributedTracingDataInternal();
    }

    public function doGetDistributedTracingData(?Span $span): ?DistributedTracingDataInternal
    {
        if (!$this->tracer->isRecording()) {
            return null;
        }

        $result = new DistributedTracingDataInternal();
        $result->traceId = $this->data->traceId;
        $result->parentId = $span === null ? $this->data->id : $span->getId();
        $result->isSampled = $this->data->isSampled;
        $result->outgoingTraceState = $this->outgoingTraceState;
        return $result;
    }

    public function getConfig(): ConfigSnapshot
    {
        return $this->config;
    }

    public function queueSpanDataToSend(SpanData $spanData): void
    {
        if ($this->tracer->getConfig()->devInternal()->dropEventAfterEnd()) {
            ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Dropping span because '
                . OptionNames::DEV_INTERNAL . ' sub-option ' . DevInternalSubOptionNames::DROP_EVENT_AFTER_END
                . ' is set'
            );
            return;
        }

        if ($this->hasEnded()) {
            $this->tracer->sendEventsToApmServer(
                [$spanData],
                [] /* <- errorsData */,
                null /* <- breakdownMetricsPerTransaction */,
                null /* <- transactionData */
            );
            return;
        }

        $this->spansDataToSend[] = $spanData;
    }

    public function reserveSpaceInErrorToSendQueue(): bool
    {
        if ($this->hasEnded() || count($this->errorsDataToSend) < $this->config->transactionMaxSpans()) {
            return true;
        }

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Starting to drop errors because of ' . OptionNames::TRANSACTION_MAX_SPANS . ' config',
            [
                'count($this->errorsDataToSend)'               => count($this->errorsDataToSend),
                OptionNames::TRANSACTION_MAX_SPANS . ' config' => $this->config->transactionMaxSpans(),
            ]
        );
        return false;
    }

    public function queueErrorDataToSend(ErrorData $errorData): void
    {
        $this->errorsDataToSend[] = $errorData;
    }

    /** @inheritDoc */
    public function discard(): void
    {
        while ($this->currentSpan !== null) {
            $spanToDiscard = $this->currentSpan;
            $this->currentSpan = $this->currentSpan->parentIfSpan();
            if (!$spanToDiscard->hasEnded()) {
                $spanToDiscard->discard();
            }
        }

        parent::discard();
    }

    /** @inheritDoc */
    public function end(?float $duration = null): void
    {
        if (!$this->endExecutionSegment($duration)) {
            return;
        }

        $this->data->prepareForSerialization();

        if ($this->tracer->getConfig()->devInternal()->dropEventAfterEnd()) {
            ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Dropping transaction because '
                . OptionNames::DEV_INTERNAL . ' sub-option ' . DevInternalSubOptionNames::DROP_EVENT_AFTER_END
                . ' is set'
            );
        } else {
            $this->tracer->sendEventsToApmServer(
                $this->spansDataToSend,
                $this->errorsDataToSend,
                $this->breakdownMetricsPerTransaction,
                $this->data
            );
        }

        if ($this->tracer->getCurrentTransaction() === $this) {
            $this->tracer->resetCurrentTransaction();
        }
    }

    public function addSpanSelfTime(string $spanType, ?string $spanSubtype, float $spanSelfTimeInMicroseconds): void
    {
        if ($this->beforeMutating() || !$this->tracer->isRecording()) {
            return;
        }

        /**
         * if addSpanSelfTime is called that means $this->breakdownMetricsPerTransaction is not null

         * Local variable to workaround PHPStan not having a way to declare that
         * $this->breakdownMetricsPerTransaction is not null
         *
         * @var BreakdownMetricsPerTransaction $breakdownMetricsPerTransaction
         */
        $breakdownMetricsPerTransaction = $this->breakdownMetricsPerTransaction;
        $breakdownMetricsPerTransaction->addSpanSelfTime(
            $spanType,
            $spanSubtype,
            $spanSelfTimeInMicroseconds
        );
    }

    /** @inheritDoc */
    protected function updateBreakdownMetricsOnEnd(float $monotonicClockNow): void
    {
        $this->doUpdateBreakdownMetricsOnEnd(
            $monotonicClockNow,
            BreakdownMetricsPerTransaction::TRANSACTION_SPAN_TYPE,
            /* subtype: */ null
        );
    }

    /** @inheritDoc */
    protected static function propertiesExcludedFromLog(): array
    {
        return array_merge(
            parent::propertiesExcludedFromLog(),
            ['config', 'context', 'currentSpan', 'spansDataToSend', 'errorsDataToSend']
        );
    }

    /** @inheritDoc */
    public function toLog(LogStreamInterface $stream): void
    {
        $currentSpanId = $this->currentSpan === null ? null : $this->currentSpan->getId();
        parent::toLogLoggableTraitImpl(
            $stream,
            /* customPropValues */
            [
                'currentSpanId'         => $currentSpanId,
                'spansDataToSendCount'  => count($this->spansDataToSend),
                'errorsDataToSendCount' => count($this->errorsDataToSend),
            ]
        );
    }
}
