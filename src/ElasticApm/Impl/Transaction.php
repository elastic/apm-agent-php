<?php

/** @noinspection PhpUndefinedClassInspection */

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Closure;
use Elastic\Apm\Impl\Config\OptionNames;
use Elastic\Apm\Impl\Config\Snapshot as ConfigSnapshot;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Util\DbgUtil;
use Elastic\Apm\Impl\Util\IdGenerator;
use Elastic\Apm\Impl\Util\RandomUtil;
use Elastic\Apm\SpanDataInterface;
use Elastic\Apm\SpanInterface;
use Elastic\Apm\TransactionInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class Transaction extends TransactionData implements TransactionInterface
{
    use ExecutionSegmentTrait;

    /** @var ConfigSnapshot */
    private $config;

    /** @var Span|null */
    private $currentSpan = null;

    /** @var Logger */
    private $logger;

    /** @var SpanDataInterface[] */
    private $spansToSend = [];

    public function __construct(Tracer $tracer, string $name, string $type, ?float $timestamp = null)
    {
        $this->constructExecutionSegmentTrait(
            $tracer,
            IdGenerator::generateId(IdGenerator::TRACE_ID_SIZE_IN_BYTES),
            $name,
            $type,
            $timestamp
        );

        $this->config = $tracer->getConfig();
        $this->logger = $this->createLogger(__NAMESPACE__, __CLASS__, __FILE__);

        $this->isSampled = $this->makeSamplingDecision();

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Transaction created', ['parentId' => $this->parentId]);
    }

    public function beginSpan(
        ?Span $parentSpan,
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
            if ($this->startedSpansCount >= $this->config->transactionMaxSpans()) {
                $isDropped = true;
                ++$this->droppedSpansCount;
                if ($this->droppedSpansCount === 1) {
                    ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
                    && $loggerProxy->log(
                        'Starting to drop spans because of ' . OptionNames::TRANSACTION_MAX_SPANS . ' config',
                        [
                            'count($this->spansToSend)'                    => count($this->spansToSend),
                            OptionNames::TRANSACTION_MAX_SPANS . ' config' => $this->config->transactionMaxSpans(),
                        ]
                    );
                }
            } else {
                ++$this->startedSpansCount;
            }
        }

        return new Span(
            $this /* <- containingTransaction*/,
            $parentSpan,
            $name,
            $type,
            $subtype,
            $action,
            $timestamp,
            $isDropped
        );
    }

    public function beginChildSpan(
        string $name,
        string $type,
        ?string $subtype = null,
        ?string $action = null,
        ?float $timestamp = null
    ): SpanInterface {
        return $this->beginSpan(
            null /* <- parentSpan */,
            $name,
            $type,
            $subtype,
            $action,
            $timestamp
        ) ?? NoopSpan::singletonInstance();
    }

    public function beginCurrentSpan(
        string $name,
        string $type,
        ?string $subtype = null,
        ?string $action = null,
        ?float $timestamp = null
    ): SpanInterface {
        $this->currentSpan = $this->beginSpan(
            $this->currentSpan,
            $name,
            $type,
            $subtype,
            $action,
            $timestamp
        );

        return $this->getCurrentSpan();
    }

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
        } finally {
            // Since endSpanEx was not called directly it should not be kept in the stack trace
            $newSpan->endSpanEx(/* numberOfStackFramesToSkip: */ 1);
        }
    }

    public function getCurrentSpan(): SpanInterface
    {
        return $this->currentSpan ?? NoopSpan::singletonInstance();
    }

    public function setCurrentSpan(?Span $newCurrentSpan): void
    {
        $this->currentSpan = $newCurrentSpan;
    }

    public function setResult(?string $result): void
    {
        if ($this->beforeMutating()) {
            return;
        }

        $this->result = $this->tracer->limitNullableKeywordString($result);
    }

    public function queueSpanToSend(Span $span): void
    {
        if ($this->hasEnded()) {
            $this->getTracer()->getEventSink()->consume([$span], /* transaction: */ null);
            return;
        }

        $this->spansToSend[] = $span;
    }

    public function end(?float $duration = null): void
    {
        if (!$this->endExecutionSegment($duration)) {
            return;
        }

        $this->getTracer()->getEventSink()->consume($this->spansToSend, $this);

        if ($this->getTracer()->getCurrentTransaction() === $this) {
            $this->getTracer()->resetCurrentTransaction();
        }
    }

    public function discard(): void
    {
        while (!is_null($this->currentSpan)) {
            $spanToDiscard = $this->currentSpan;
            $this->currentSpan = $spanToDiscard->parentSpan();
            if (!$spanToDiscard->hasEnded()) {
                $spanToDiscard->discard();
            }
        }
        $this->discardExecutionSegment();
    }

    private function makeSamplingDecision(): bool
    {
        if ($this->tracer->getConfig()->transactionSampleRate() === 0.0) {
            return false;
        }
        if ($this->tracer->getConfig()->transactionSampleRate() === 1.0) {
            return true;
        }

        return RandomUtil::generate01Float() < $this->tracer->getConfig()->transactionSampleRate();
    }
}
