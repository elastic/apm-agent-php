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
use Elastic\Apm\Impl\BackendComm\SerializationUtil;
use Elastic\Apm\Impl\Log\LogCategory;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Log\LogStreamInterface;
use Elastic\Apm\Impl\Util\ObserverSet;
use Elastic\Apm\Impl\Util\TimeUtil;
use Elastic\Apm\SpanContextInterface;
use Elastic\Apm\SpanInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class Span extends ExecutionSegment implements SpanInterface, SpanToSendInterface
{
    /** @var string */
    public $parentId;

    /** @var string */
    public $transactionId;

    /** @var ?string */
    public $action = null;

    /** @var ?string */
    public $subtype = null;

    /** @var null|StackTraceFrame[] */
    public $stackTrace = null;

    /** @var ?SpanContext */
    public $context = null;

    /** @var Logger */
    private $logger;

    /** @var Transaction */
    private $containingTransaction;

    /** @var ExecutionSegment */
    private $parentExecutionSegment;

    /** @var bool */
    private $isDropped;

    /** @var ObserverSet<Span> */
    public $onAboutToEnd;

    /** @var bool */
    protected $hasChildren = false;

    /** @var bool */
    private $isCompressible = false;

    /** @var ?SpanComposite */
    public $composite = null;

    public function __construct(
        Tracer $tracer,
        Transaction $containingTransaction,
        ExecutionSegment $parentExecutionSegment,
        string $name,
        string $type,
        ?string $subtype,
        ?string $action,
        ?float $timestamp,
        bool $isDropped,
        ?float $sampleRate
    ) {
        $this->parentExecutionSegment = $parentExecutionSegment;
        $this->containingTransaction = $containingTransaction;

        parent::__construct(
            $tracer,
            $this->parentExecutionSegment,
            $containingTransaction->getTraceId(),
            $name,
            $type,
            $sampleRate,
            $timestamp
        );

        $this->setSubtype($subtype);
        $this->setAction($action);

        $this->transactionId = $containingTransaction->getId();

        $this->parentId = $this->parentExecutionSegment->getId();

        $this->logger = $this->containingTransaction()->tracer()->loggerFactory()
                             ->loggerForClass(LogCategory::PUBLIC_API, __NAMESPACE__, __CLASS__, __FILE__)
                             ->addContext('this', $this);

        $this->isDropped = $isDropped;

        $this->onAboutToEnd = new ObserverSet();

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Span created');

        $parentExecutionSegment->onChildSpanAboutToStart($this);
    }

    /** @inheritDoc */
    public function containingTransaction(): Transaction
    {
        return $this->containingTransaction;
    }

    /** @inheritDoc */
    public function parentExecutionSegment(): ?ExecutionSegment
    {
        return $this->parentExecutionSegment;
    }

    public function shouldBeSentToApmServer(): bool
    {
        return $this->containingTransaction->isSampled() && (!$this->isDropped);
    }

    /** @inheritDoc */
    public function getParentId(): string
    {
        return $this->parentId;
    }

    /** @inheritDoc */
    public function getTransactionId(): string
    {
        return $this->transactionId;
    }

    /** @inheritDoc */
    public function context(): SpanContextInterface
    {
        if (!$this->isSampled()) {
            return NoopSpanContext::singletonInstance();
        }

        if ($this->context === null) {
            $this->context = new SpanContext($this);
        }

        return $this->context;
    }

    /** @inheritDoc */
    public function setAction(?string $action): void
    {
        if ($this->beforeMutating()) {
            return;
        }

        $this->action = $this->containingTransaction->tracer()->limitNullableKeywordString($action);
    }

    /** @inheritDoc */
    public function setSubtype(?string $subtype): void
    {
        if ($this->beforeMutating()) {
            return;
        }

        $this->subtype = $this->containingTransaction->tracer()->limitNullableKeywordString($subtype);
    }

    public static function setServiceFor(SpanInterface $span, ?string $targetType, ?string $targetName, string $destinationName, string $destinationResource, string $destinationType): void
    {
        $span->context()->service()->target()->setType($targetType);
        $span->context()->service()->target()->setName($targetName);

        // destination.service is deprecated in favor of service.target
        $span->context()->destination()->setService($destinationName, $destinationResource, $destinationType);
    }

    public function setCompressible(bool $isCompressible): void
    {
        $this->isCompressible = $isCompressible;
    }

    /** @inheritDoc */
    public function getDistributedTracingDataInternal(): ?DistributedTracingDataInternal
    {
        $spanAsParent = $this->shouldBeSentToApmServer() ? $this : null;
        return $this->containingTransaction->doGetDistributedTracingData($spanAsParent);
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
            $this->containingTransaction->beginSpan(
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
    public function dispatchCreateError(ErrorExceptionData $errorExceptionData): ?string
    {
        $spanForError = $this->shouldBeSentToApmServer() ? $this : null;
        return $this->containingTransaction->tracer()->doCreateError($errorExceptionData, $this->containingTransaction, $spanForError);
    }

    public function isCompressionEligible(): bool
    {
        if (!$this->isCompressible) {
            ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log('This span is not eligible for compression because it is not marked as compressible');
            return false;
        }

        if ($this->wasPropogatedViaDistributedTracing) {
            ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log('This span is not eligible for compression because its ID was propogated via distributed tracing');
            return false;
        }

        if ($this->hasChildren) {
            ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log('This span is not eligible for compression because it has children');
            return false;
        }

        if ($this->outcome !== null && $this->outcome !== Constants::OUTCOME_SUCCESS) {
            ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log('This span is not eligible its outcome is present and it is not success', ['outcome' => $this->outcome]);
            return false;
        }

        ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('This span is eligible for compression');

        return true;
    }

    private function getServiceTarget(): ?SpanContextServiceTarget
    {
        return ($this->context === null || $this->context->service === null) ? null : $this->context->service->target;
    }

    private function isSameKind(Span $other): bool
    {
        /**
         * @link https://github.com/elastic/apm/blob/4a5e72b3cee430a839c0adda645c71d4eb0a66bb/specs/agents/handling-huge-traces/tracing-spans-compress.md#consecutive-same-kind-compression-strategy
         */
        return ($this->type === $other->type)
               && ($this->subtype === $other->subtype)
               && SpanContextServiceTarget::areNullableEqual($this->getServiceTarget(), $other->getServiceTarget());
    }

    public function tryToAddToCompress(Span $sibling): bool
    {
        $logTraceProxy = $this->logger->ifTraceLevelEnabledNoLine(__FUNCTION__);
        if ($this->logger->isTraceLevelEnabled()) {
            $logger = $this->logger->inherit()->addContext('sibling', $sibling);
            $logTraceProxy = $logger->ifTraceLevelEnabledNoLine(__FUNCTION__);
        }

        $logTraceProxy && $logTraceProxy->log(__LINE__, 'Entered');

        if ($this->composite === null) {
            $compressionStrategy = $this->canCompressFirstPair($sibling);
            if ($compressionStrategy === null) {
                $logTraceProxy && $logTraceProxy->log(__LINE__, 'Exiting - cannot compress first pair');
                return false;
            }
            if ($compressionStrategy === Constants::COMPRESSION_STRATEGY_SAME_KIND) {
                $this->name = (($serviceTarget = $this->getServiceTarget()) === null)
                    ? self::buildSameKindCompressedCompositeName(null, null)
                    : self::buildSameKindCompressedCompositeName($serviceTarget->type, $serviceTarget->name);
            }
            $this->composite = new SpanComposite($compressionStrategy, $this->duration);
        } else {
            if (!$this->canAddToCompositeToCompress($this->composite, $sibling)) {
                $logTraceProxy && $logTraceProxy->log(__LINE__, 'Exiting - cannot add sibling');
                return false;
            }
        }

        $this->composite->durationsSum += $sibling->duration;
        ++$this->composite->count;
        $this->recalcDurationForComposite($sibling);
        /**
         * When a span is compressed into a composite, span_count.reported should ONLY count the compressed composite as a single span.
         * Spans that have been compressed into the composite should not be counted.
         *
         * @link https://github.com/elastic/apm/blob/5e1bfbc95fa0358ef195cedba8cb1be281988227/specs/agents/handling-huge-traces/tracing-spans-compress.md#effects-on-span-count
         */
        --$this->containingTransaction->startedSpansCount;

        $logTraceProxy && $logTraceProxy->log(__LINE__, 'Exiting - added sibling');
        return true;
    }

    private function canCompressFirstPair(Span $sibling): ?string
    {
        if (($loggerProxyTrace = $this->logger->ifTraceLevelEnabledNoLine(__FUNCTION__)) !== null) {
            $localLogger = $this->logger->inherit()->addAllContext(
                [
                    'this'    => ['name' => $this->name, 'type' => $this->type, 'duration' => $this->duration],
                    'sibling' => ['name' => $sibling->name, 'type' => $sibling->type, 'duration' => $sibling->duration],
                ]
            );
            $loggerProxyTrace = $localLogger->ifTraceLevelEnabledNoLine(__FUNCTION__);
        }

        if (!$this->isSameKind($sibling)) {
            $loggerProxyTrace && $loggerProxyTrace->log(__LINE__, 'Cannot compress because not even same kind');
            return null;
        }

        $config = $this->containingTransaction->tracer()->getConfig();
        $exactMatchMaxDuration = $config->spanCompressionExactMatchMaxDuration();
        if ($this->name === $sibling->name) {
            if ($this->duration <= $exactMatchMaxDuration && $sibling->duration <= $exactMatchMaxDuration) {
                $loggerProxyTrace && $loggerProxyTrace->log(__LINE__, 'Can compress as ' . Constants::COMPRESSION_STRATEGY_EXACT_MATCH);
                return Constants::COMPRESSION_STRATEGY_EXACT_MATCH;
            } else {
                /**
                 * Note that if the spans are exact match but duration threshold requirement is not satisfied we just stop compression sequence.
                 * In particular it means that the implementation should not proceed to try same kind strategy.
                 * Otherwise user would have to lower both span_compression_exact_match_max_duration and span_compression_same_kind_max_duration
                 * to prevent longer exact match spans from being compressed.
                 *
                 * @link https://github.com/elastic/apm/blob/e528576a5b0f3e95fe3c1da493466882fa7d8329/specs/agents/handling-huge-traces/tracing-spans-compress.md?plain=1#L200
                 */
                $loggerProxyTrace && $loggerProxyTrace->log(
                    __LINE__,
                    'Cannot compress as ' . Constants::COMPRESSION_STRATEGY_EXACT_MATCH . ' because one of the durations is above configured threshold',
                    ['exactMatchMaxDuration (ms)' => $exactMatchMaxDuration]
                );
                return null;
            }
        }

        $sameKindMaxDuration = $config->spanCompressionSameKindMaxDuration();
        if ($this->duration <= $sameKindMaxDuration && $sibling->duration <= $sameKindMaxDuration) {
            $loggerProxyTrace && $loggerProxyTrace->log(__LINE__, 'Can compress as ' . Constants::COMPRESSION_STRATEGY_SAME_KIND);
            return Constants::COMPRESSION_STRATEGY_SAME_KIND;
        } else {
            $loggerProxyTrace && $loggerProxyTrace->log(
                __LINE__,
                'Cannot compress as ' . Constants::COMPRESSION_STRATEGY_SAME_KIND . ' because one of the durations is above configured threshold',
                ['sameKindMaxDuration (ms)' => $sameKindMaxDuration]
            );
            return null;
        }
    }

    public static function buildSameKindCompressedCompositeName(?string $serviceTargetType, ?string $serviceTargetName): string
    {
        /**
         * @link https://github.com/elastic/apm/blob/4a5e72b3cee430a839c0adda645c71d4eb0a66bb/specs/agents/handling-huge-traces/tracing-spans-compress.md#consecutive-same-kind-compression-strategy
         */
        $prefix = 'Calls to ';

        if ($serviceTargetType === null) {
            if ($serviceTargetName === null) {
                return $prefix . 'unknown';
            }
            return $prefix . $serviceTargetName;
        }

        if ($serviceTargetName === null) {
            return $prefix . $serviceTargetType;
        }

        return $prefix . $serviceTargetType . '/' . $serviceTargetName;
    }

    public function calcEndTimestamp(): float
    {
        return $this->timestamp + TimeUtil::millisecondsToMicroseconds($this->duration);
    }

    private function recalcDurationForComposite(Span $sibling): void
    {
        $beginTimestamp = min($this->timestamp, $sibling->timestamp);
        $endTimestamp = max($this->calcEndTimestamp(), $sibling->calcEndTimestamp());
        $this->duration = TimeUtil::microsecondsToMilliseconds($endTimestamp - $beginTimestamp);
    }

    private function canAddToCompositeToCompress(SpanComposite $compositeData, Span $sibling): bool
    {
        $config = $this->containingTransaction->tracer()->getConfig();
        switch ($compositeData->compressionStrategy) {
            case Constants::COMPRESSION_STRATEGY_EXACT_MATCH:
                return $this->isSameKind($sibling)
                       && $this->name === $sibling->name
                       && $sibling->duration <= $config->spanCompressionExactMatchMaxDuration();

            case Constants::COMPRESSION_STRATEGY_SAME_KIND:
                return $this->isSameKind($sibling)
                       && $sibling->duration <= $config->spanCompressionSameKindMaxDuration();

            default:
                ($loggerProxy = $this->logger->ifCriticalLevelEnabled(__LINE__, __FUNCTION__))
                && $loggerProxy->log(
                    'Unexpected value for compression strategy: `' . $compositeData->compressionStrategy . '\''
                );
                return false;
        }
    }

    protected function onChildSpanAboutToStart(Span $child): void
    {
        parent::onChildSpanAboutToStart($child);
        $this->hasChildren = true;
    }

    /** @inheritDoc */
    public function endSpanEx(int $numberOfStackFramesToSkip, ?float $duration = null): void
    {
        if (!$this->endExecutionSegment($duration)) {
            return;
        }

        $this->onAboutToEnd->callCallbacks($this);

        if ($this->shouldBeSentToApmServer()) {
            /**
             * stack_trace_limit
             *      0 - stack trace collection should be disabled
             *      any positive integer value - the value is the maximum number of frames to collect
             *      -1  - all frames should be collected
             */
            $stackTraceLimit = $this->containingTransaction->getStackTraceLimitConfig();
            if ($stackTraceLimit !== 0) {
                // This method is part of public API so it should be kept in the stack trace
                // if $numberOfStackFramesToSkip is 0
                $this->stackTrace = $this->containingTransaction()->tracer()->stackTraceUtil()->captureInApmFormat($numberOfStackFramesToSkip + 1);
            }	
            $this->prepareForSerialization();
            $this->parentExecutionSegment->onChildSpanEnded($this);
        }

        if ($this->containingTransaction->getCurrentSpan() === $this) {
            $this->containingTransaction->setCurrentSpan($this->parentIfSpan());
        }
    }

    public function parentIfSpan(): ?Span
    {
        // parentExecutionSegment is either a parent span or a containing transaction
        if ($this->parentExecutionSegment === $this->containingTransaction) {
            return null;
        }

        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->parentExecutionSegment; // @phpstan-ignore-line
        // It seems there's no way to tell PHPStan that $this->parentExecutionSegment is a Span
    }

    /** @inheritDoc */
    public function end(?float $duration = null): void
    {
        // Since endSpanEx was not called directly it should not be kept in the stack trace
        $this->endSpanEx(/* numberOfStackFramesToSkip: */ 1, $duration);
    }

    /** @inheritDoc */
    protected function updateBreakdownMetricsOnEnd(float $monotonicClockNow): void
    {
        $this->doUpdateBreakdownMetricsOnEnd($monotonicClockNow, $this->type, $this->subtype);
    }

    private function prepareForSerialization(): void
    {
        SerializationUtil::prepareForSerialization(/* ref */ $this->context);
    }

    /** @inheritDoc */
    public function jsonSerialize()
    {
        $result = SerializationUtil::preProcessResult(parent::jsonSerialize());

        SerializationUtil::addNameValue('parent_id', $this->parentId, /* ref */ $result);
        SerializationUtil::addNameValue('transaction_id', $this->transactionId, /* ref */ $result);
        SerializationUtil::addNameValueIfNotNull('action', $this->action, /* ref */ $result);
        SerializationUtil::addNameValueIfNotNull('subtype', $this->subtype, /* ref */ $result);
        SerializationUtil::addNameValueIfNotNull('stacktrace', $this->stackTrace, /* ref */ $result);

        SerializationUtil::addNameValueIfNotNull('context', $this->context, /* ref */ $result);

        SerializationUtil::addNameValueIfNotNull('composite', $this->composite, /* ref */ $result);

        return SerializationUtil::postProcessResult($result);
    }

    /** @inheritDoc */
    protected static function propertiesExcludedFromLog(): array
    {
        return array_merge(
            parent::propertiesExcludedFromLog(),
            ['containingTransaction', 'parentExecutionSegment', 'stackTrace', 'context']
        );
    }

    /** @inheritDoc */
    public function toLog(LogStreamInterface $stream): void
    {
        parent::toLogLoggableTraitImpl(
            $stream,
            /* customPropValues */
            [
                'containingTransaction ID'  => $this->containingTransaction->getId(),
                'parentExecutionSegment ID' => $this->parentExecutionSegment->getId(),
                'stackTrace count'          => $this->stackTrace === null ? null : count($this->stackTrace),
                'context === null'          => $this->context === null,
            ]
        );
    }
}
