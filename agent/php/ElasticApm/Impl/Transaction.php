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
use Elastic\Apm\Impl\BackendComm\SerializationUtil;
use Elastic\Apm\Impl\BreakdownMetrics\PerTransaction as BreakdownMetricsPerTransaction;
use Elastic\Apm\Impl\Config\OptionNames;
use Elastic\Apm\Impl\Config\Snapshot as ConfigSnapshot;
use Elastic\Apm\Impl\Log\LogCategory;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Log\LogStreamInterface;
use Elastic\Apm\Impl\Util\IdGenerator;
use Elastic\Apm\Impl\Util\ObserverSet;
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
    /** @var ?string */
    public $parentId = null;

    /** @var int */
    public $startedSpansCount = 0;

    /** @var int */
    public $droppedSpansCount = 0;

    /** @var ?string */
    public $result = null;

    /** @var bool */
    public $isSampled;

    /** @var ?TransactionContext */
    public $context = null;

    /** @var Tracer */
    private $tracer;

    /** @var ConfigSnapshot */
    private $config;

    /** @var ?Span */
    private $currentSpan = null;

    /** @var Logger */
    private $logger;

    /** @var int */
    public $numberOfErrorsSent = 0;

    /** @var ?BreakdownMetricsPerTransaction */
    private $breakdownMetricsPerTransaction = null;

    /** @var ?string */
    private $outgoingTraceState;

    /** @var ObserverSet<Transaction> */
    public $onAboutToEnd;

    /** @var ObserverSet<?Span> */
    public $onCurrentSpanChanged;

    public function __construct(TransactionBuilder $builder)
    {
        $this->tracer = $builder->tracer;
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
            $this->parentId = $distributedTracingData->parentId;
            $isSampled = $distributedTracingData->isSampled;
            $sampleRateToMarkTransaction = $distributedTracingData->sampleRate;
            $this->outgoingTraceState = $distributedTracingData->outgoingTraceState;
        }

        parent::__construct(
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

        $this->isSampled = $isSampled;

        $this->onCurrentSpanChanged = new ObserverSet();
        $this->onAboutToEnd = new ObserverSet();

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Transaction created');
    }

    private static function extractDistributedTracingData(TransactionBuilder $builder): ?DistributedTracingDataInternal
    {
        /** @var string[] $traceParentHeaderValues */
        $traceParentHeaderValues = [];
        /** @var string[] $traceStateHeaderValues */
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
        return $this->parentId;
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
        return $this->isSampled;
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
            $this->context = new TransactionContext($this);
        }

        return $this->context;
    }

    public function cloneContextData(): ?TransactionContext
    {
        if ($this->context === null) {
            return null;
        }
        return clone $this->context;
    }

    /** @inheritDoc */
    public function setResult(?string $result): void
    {
        if ($this->beforeMutating()) {
            return;
        }

        $this->result = $this->tracer->limitNullableKeywordString($result);
    }

    /** @inheritDoc */
    public function getResult(): ?string
    {
        return $this->result;
    }

    /** @inheritDoc */
    public function getCurrentSpan(): SpanInterface
    {
        return $this->currentSpan ?? NoopSpan::singletonInstance();
    }

    public function setCurrentSpan(?Span $newCurrentSpan): void
    {
        $this->currentSpan = $newCurrentSpan;
        $this->onCurrentSpanChanged->callCallbacks($this->currentSpan);
    }

    public function getCurrentExecutionSegment(): ExecutionSegmentInterface
    {
        return $this->currentSpan ?? $this;
    }

    public function tryToAllocateStartedSpan(): bool
    {
        if ($this->startedSpansCount < $this->config->transactionMaxSpans()) {
            ++$this->startedSpansCount;
            return true;
        }

        ++$this->droppedSpansCount;
        if ($this->droppedSpansCount === 1) {
            ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Starting to drop spans because of ' . OptionNames::TRANSACTION_MAX_SPANS . ' config',
                [OptionNames::TRANSACTION_MAX_SPANS . ' config' => $this->config->transactionMaxSpans()]
            );
        }
        return false;
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
        if ($this->isSampled) {
            $isDropped = !$this->tryToAllocateStartedSpan();
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
            $this->sampleRate
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
        $newCurrentSpan = $this->beginSpan(
            $this->currentSpan ?? $this /* <- parentExecutionSegment */,
            $name,
            $type,
            $subtype,
            $action,
            $timestamp
        );
        $this->setCurrentSpan($newCurrentSpan);
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
        if ($this->parentId === null) {
            $this->parentId = IdGenerator::generateId(Constants::EXECUTION_SEGMENT_ID_SIZE_IN_BYTES);
            ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Setting parent ID for already existing transaction',
                ['parentId' => $this->parentId]
            );
        }

        return $this->parentId;
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
        $result->traceId = $this->traceId;
        $result->parentId = $span === null ? $this->id : $span->getId();
        $result->isSampled = $this->isSampled;
        $result->outgoingTraceState = $this->outgoingTraceState;
        return $result;
    }

    public function getConfig(): ConfigSnapshot
    {
        return $this->config;
    }

    /** @inheritDoc */
    public function discard(): void
    {
        while ($this->currentSpan !== null) {
            $spanToDiscard = $this->currentSpan;
            $this->setCurrentSpan($this->currentSpan->parentIfSpan());
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

        $this->onAboutToEnd->callCallbacks($this);

        $this->prepareForSerialization();

        $this->tracer->sendTransactionToApmServer($this->breakdownMetricsPerTransaction, $this);

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

    private function prepareForSerialization(): void
    {
        SerializationUtil::prepareForSerialization(/* ref */ $this->context);
    }

    /** @inheritDoc */
    public function jsonSerialize()
    {
        $result = SerializationUtil::preProcessResult(parent::jsonSerialize());

        SerializationUtil::addNameValueIfNotNull('parent_id', $this->parentId, /* ref */ $result);

        $spanCountSubObject = ['started' => $this->startedSpansCount];
        if ($this->droppedSpansCount != 0) {
            $spanCountSubObject['dropped'] = $this->droppedSpansCount;
        }
        SerializationUtil::addNameValue('span_count', $spanCountSubObject, /* ref */ $result);

        SerializationUtil::addNameValueIfNotNull('result', $this->result, /* ref */ $result);

        // https://github.com/elastic/apm-server/blob/7.0/docs/spec/transactions/transaction.json#L72
        // 'sampled' is optional and defaults to true.
        if (!$this->isSampled) {
            SerializationUtil::addNameValue('sampled', $this->isSampled, /* ref */ $result);
        }

        SerializationUtil::addNameValueIfNotNull('context', $this->context, /* ref */ $result);

        return SerializationUtil::postProcessResult($result);
    }

    /** @inheritDoc */
    protected static function propertiesExcludedFromLog(): array
    {
        return array_merge(parent::propertiesExcludedFromLog(), ['config', 'currentSpan']);
    }

    /** @inheritDoc */
    public function toLog(LogStreamInterface $stream): void
    {
        $currentSpanId = $this->currentSpan === null ? null : $this->currentSpan->getId();
        parent::toLogLoggableTraitImpl(
            $stream,
            /* customPropValues */
            ['currentSpanId' => $currentSpanId]
        );
    }
}
