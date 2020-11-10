<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Closure;
use Elastic\Apm\DistributedTracingData;
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

    /** @var ErrorData[] */
    private $errorsDataToSend = [];

    /** @var TransactionContext|null */
    private $context = null;

    public function __construct(
        Tracer $tracer,
        string $name,
        string $type,
        ?float $timestamp = null,
        ?DistributedTracingData $distributedTracingData = null
    ) {
        $this->data = new TransactionData();

        if (is_null($distributedTracingData)) {
            $traceId = IdGenerator::generateId(Constants::TRACE_ID_SIZE_IN_BYTES);
        } else {
            $traceId = $distributedTracingData->traceId;
            $this->data->parentId = $distributedTracingData->parentId;
        }

        parent::__construct(
            $this->data,
            $tracer,
            $traceId,
            $name,
            $type,
            $timestamp
        );

        $this->config = $tracer->getConfig();

        $this->logger = $this->tracer->loggerFactory()
                                     ->loggerForClass(LogCategory::PUBLIC_API, __NAMESPACE__, __CLASS__, __FILE__)
                                     ->addContext('this', $this);

        $this->data->isSampled = is_null($distributedTracingData)
            ? $this->makeSamplingDecision()
            : $distributedTracingData->isSampled;

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Transaction created');
    }

    /** @inheritDoc */
    public function getParentId(): ?string
    {
        return $this->data->parentId;
    }

    public function getType(): string
    {
        return $this->data->type;
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

    /** @inheritDoc */
    public function isSampled(): bool
    {
        return $this->data->isSampled;
    }

    /** @inheritDoc */
    public function context(): TransactionContextInterface
    {
        if (!$this->isSampled()) {
            return NoopTransactionContext::singletonInstance();
        }

        if (is_null($this->context)) {
            $this->data->context = new TransactionContextData();
            $this->context = new TransactionContext($this, $this->data->context);
        }

        return $this->context;
    }

    public function cloneContextData(): ?TransactionContextData
    {
        if (is_null($this->data->context)) {
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
                null /* <- parentSpan */,
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
            $this->currentSpan,
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
            $newSpan->createError($throwable);
            throw $throwable;
        } finally {
            // Since endSpanEx was not called directly it should not be kept in the stack trace
            $newSpan->endSpanEx(/* numberOfStackFramesToSkip: */ 1);
        }
    }

    /** @inheritDoc */
    public function createError(Throwable $throwable): ?string
    {
        if (is_null($this->currentSpan)) {
            return $this->tracer->doCreateError($throwable, /* transaction: */ $this, /* span */ null);
        }

        return $this->currentSpan->createError($throwable);
    }

    /** @inheritDoc */
    public function getDistributedTracingData(): ?DistributedTracingData
    {
        if (is_null($this->currentSpan)) {
            return $this->doGetDistributedTracingData(/* span */ null);
        }

        return $this->currentSpan->getDistributedTracingData();
    }

    public function doGetDistributedTracingData(?Span $span): ?DistributedTracingData
    {
        if (!$this->tracer->isRecording()) {
            return null;
        }

        $result = new DistributedTracingData();
        $result->traceId = $this->data->traceId;
        $result->parentId = is_null($span) ? $this->data->id : $span->getId();
        $result->isSampled = $this->data->isSampled;
        return $result;
    }

    public function queueSpanDataToSend(SpanData $spanData): void
    {
        if ($this->hasEnded()) {
            $this->tracer->sendEventsToApmServer([$spanData], /* errorsData */ [], /* transaction: */ null);
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

    public function discard(): void
    {
        while (!is_null($this->currentSpan)) {
            $spanToDiscard = $this->currentSpan;
            $this->currentSpan = $spanToDiscard->parentSpan();
            if (!$spanToDiscard->hasEnded()) {
                $spanToDiscard->discard();
            }
        }

        parent::discard();
    }

    public function end(?float $duration = null): void
    {
        if (!$this->endExecutionSegment($duration)) {
            return;
        }

        if ((!is_null($this->data->context)) && $this->data->context->isEmpty()) {
            $this->data->context = null;
        }

        $this->tracer->sendEventsToApmServer($this->spansDataToSend, $this->errorsDataToSend, $this->data);

        if ($this->tracer->getCurrentTransaction() === $this) {
            $this->tracer->resetCurrentTransaction();
        }
    }

    /**
     * @return string[]
     */
    protected static function propertiesExcludedFromLog(): array
    {
        return array_merge(
            parent::propertiesExcludedFromLog(),
            ['config', 'logger', 'spansDataToSend']
        );
    }

    /** @inheritDoc */
    public function toLog(LogStreamInterface $stream): void
    {
        $currentSpanId = is_null($this->currentSpan) ? null : $this->currentSpan->getId();
        parent::toLogLoggableTraitImpl($stream, /* customPropValues */ ['currentSpan' => $currentSpanId]);
    }
}
