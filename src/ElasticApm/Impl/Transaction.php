<?php

/** @noinspection PhpUndefinedClassInspection */

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Closure;
use Elastic\Apm\Impl\Config\OptionNames;
use Elastic\Apm\Impl\Config\Snapshot as ConfigSnapshot;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Log\LogStreamInterface;
use Elastic\Apm\Impl\Util\IdGenerator;
use Elastic\Apm\Impl\Util\RandomUtil;
use Elastic\Apm\SpanInterface;
use Elastic\Apm\TransactionContextInterface;
use Elastic\Apm\TransactionInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class Transaction extends ExecutionSegment implements TransactionInterface, TransactionContextInterface
{
    /** @var TransactionData */
    private $data;

    /** @var ConfigSnapshot */
    private $config;

    /** @var Span|null */
    private $currentSpan = null;

    /** @var Logger */
    private $logger;

    /** @var SpanData[] */
    private $spansDataToSend = [];

    public function __construct(Tracer $tracer, string $name, string $type, ?float $timestamp = null)
    {
        $this->data = new TransactionData();

        parent::__construct(
            $tracer,
            IdGenerator::generateId(IdGenerator::TRACE_ID_SIZE_IN_BYTES),
            $name,
            $type,
            $timestamp
        );

        $this->config = $tracer->getConfig();

        $this->logger = $this->createLogger(__NAMESPACE__, __CLASS__, __FILE__);

        $this->data->isSampled = $this->makeSamplingDecision();

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Transaction created');
    }

    protected function executionSegmentData(): ExecutionSegmentData
    {
        return $this->data;
    }

    private function lazyContextData(): TransactionContextData
    {
        if (is_null($this->data->context)) {
            $this->data->context = new TransactionContextData();
        }
        return $this->data->context;
    }

    protected function executionSegmentContextData(): ExecutionSegmentContextData
    {
        return $this->lazyContextData();
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
        if ($this->data->isSampled) {
            if ($this->data->startedSpansCount >= $this->config->transactionMaxSpans()) {
                $isDropped = true;
                ++$this->data->droppedSpansCount;
                if ($this->data->droppedSpansCount === 1) {
                    ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
                    && $loggerProxy->log(
                        'Starting to drop spans because of ' . OptionNames::TRANSACTION_MAX_SPANS . ' config',
                        [
                            'count($this->spansDataToSend)'                    => count($this->spansDataToSend),
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
        return
            $this->beginSpan(
                null /* <- parentSpan */,
                $name,
                $type,
                $subtype,
                $action,
                $timestamp
            )
            ?? NoopSpan::singletonInstance();
    }

    public function captureChildSpan(
        string $name,
        string $type,
        Closure $callback,
        ?string $subtype = null,
        ?string $action = null,
        ?float $timestamp = null
    ) {
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

    public function context(): TransactionContextInterface
    {
        if ($this->beforeMutating() || (!$this->isSampled())) {
            return NoopTransactionContext::singletonInstance();
        }

        return $this;
    }

    public function getParentId(): ?string
    {
        return $this->data->parentId;
    }

    /**
     * Transactions that are 'sampled' will include all available information
     * Transactions that are not sampled will not have 'spans' or 'context'.
     *
     * @link https://github.com/elastic/apm-server/blob/7.0/docs/spec/transactions/transaction.json#L72
     */
    public function isSampled(): bool
    {
        return $this->data->isSampled;
    }

    public function setResult(?string $result): void
    {
        if ($this->beforeMutating()) {
            return;
        }

        $this->data->result = $this->tracer->limitNullableKeywordString($result);
    }

    public function getResult(): ?string
    {
        return $this->data->result;
    }

    public function queueSpanDataToSend(SpanData $spanData): void
    {
        if ($this->hasEnded()) {
            $this->tracer->sendEventsToApmServer([$spanData], /* transaction: */ null);
            return;
        }

        $this->spansDataToSend[] = $spanData;
    }

    public function end(?float $duration = null): void
    {
        if (!$this->endExecutionSegment($duration)) {
            return;
        }

        if (!is_null($this->data->context) && $this->isContextEmpty()) {
            $this->data->context = null;
        }

        $this->tracer->sendEventsToApmServer($this->spansDataToSend, $this->data);

        if ($this->tracer->getCurrentTransaction() === $this) {
            $this->tracer->resetCurrentTransaction();
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

    /**
     * @return array<string>
     */
    protected static function propertiesExcludedFromLog(): array
    {
        return array_merge(
            parent::propertiesExcludedFromLog(),
            ['config', 'logger', 'spansDataToSend']
        );
    }

    public function toLog(LogStreamInterface $stream): void
    {
        $currentSpanId = is_null($this->currentSpan) ? null : $this->currentSpan->getId();
        parent::toLogLoggableTraitImpl($stream, /* customPropValues */ ['currentSpan' => $currentSpanId]);
    }
}
