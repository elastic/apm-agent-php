<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Closure;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\SpanContextInterface;
use Elastic\Apm\SpanInterface;
use Throwable;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class Span extends ExecutionSegment implements SpanInterface, SpanContextInterface
{
    /** @var SpanData */
    private $data;

    /** @var Logger */
    private $logger;

    /** @var Transaction */
    private $containingTransaction;

    /** @var Span|null */
    private $parentSpan;

    /** @var bool */
    private $isDropped;

    public function __construct(
        Tracer $tracer,
        Transaction $containingTransaction,
        ?Span $parentSpan,
        string $name,
        string $type,
        ?string $subtype,
        ?string $action,
        ?float $timestamp,
        bool $isDropped
    ) {
        $this->data = new SpanData();

        parent::__construct(
            $tracer,
            $containingTransaction->getTraceId(),
            $name,
            $type,
            $timestamp
        );

        $this->setSubtype($subtype);
        $this->setAction($action);

        $this->containingTransaction = $containingTransaction;
        $this->data->transactionId = $containingTransaction->getId();

        $this->parentSpan = $parentSpan;
        $this->data->parentId = is_null($parentSpan) ? $containingTransaction->getId() : $parentSpan->getId();

        $this->logger = $this->createLogger(__NAMESPACE__, __CLASS__, __FILE__);

        $this->isDropped = $isDropped;

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Span created');
    }

    /**
     * @return array<string>
     */
    protected static function propertiesExcludedFromLog(): array
    {
        return array_merge(
            parent::propertiesExcludedFromLog(),
            ['containingTransaction', 'parentSpan', 'logger', 'stacktrace']
        );
    }

    /** @inheritDoc */
    public function isSampled(): bool
    {
        return $this->containingTransaction->isSampled();
    }

    public function parentSpan(): ?Span
    {
        return $this->parentSpan;
    }

    private function shouldBeSentToApmServer(): bool
    {
        return $this->containingTransaction->isSampled() && (!$this->isDropped);
    }

    /** @inheritDoc */
    public function getParentId(): string
    {
        return $this->data->parentId;
    }

    /** @inheritDoc */
    public function getTransactionId(): string
    {
        return $this->data->transactionId;
    }

    /** @inheritDoc */
    protected function executionSegmentData(): ExecutionSegmentData
    {
        return $this->data;
    }

    /** @inheritDoc */
    protected function executionSegmentContextData(): ExecutionSegmentContextData
    {
        return $this->lazyContextData();
    }

    private function lazyContextData(): SpanContextData
    {
        if (is_null($this->data->context)) {
            $this->data->context = new SpanContextData();
        }
        return $this->data->context;
    }

    /** @inheritDoc */
    public function context(): SpanContextInterface
    {
        if (!$this->isSampled()) {
            return NoopSpanContext::singletonInstance();
        }

        return $this;
    }

    /** @inheritDoc */
    public function setAction(?string $action): void
    {
        if ($this->beforeMutating()) {
            return;
        }

        $this->data->action = $this->tracer->limitNullableKeywordString($action);
    }

    /** @inheritDoc */
    public function setSubtype(?string $subtype): void
    {
        if ($this->beforeMutating()) {
            return;
        }

        $this->data->subtype = $this->tracer->limitNullableKeywordString($subtype);
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
                $this /* <- parentSpan */,
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
    public function createError(Throwable $throwable): ?string
    {
        $spanForError = $this->shouldBeSentToApmServer() ? $this : null;
        return $this->tracer->doCreateError($throwable, $this->containingTransaction, $spanForError);
    }

    /** @inheritDoc */
    public function endSpanEx(int $numberOfStackFramesToSkip, ?float $duration = null): void
    {
        if (!$this->endExecutionSegment($duration)) {
            return;
        }

        // This method is part of public API so it should be kept in the stack trace
        // if $numberOfStackFramesToSkip is 0
        $this->data->stacktrace = StacktraceUtil::captureCurrent(
            $numberOfStackFramesToSkip,
            true /* <- hideElasticApmImpl */
        );

        if (!is_null($this->data->context) && $this->isContextEmpty()) {
            $this->data->context = null;
        }

        if ($this->shouldBeSentToApmServer()) {
            $this->containingTransaction->queueSpanDataToSend($this->data);
        }

        if ($this->containingTransaction->getCurrentSpan() === $this) {
            $this->containingTransaction->setCurrentSpan($this->parentSpan);
        }
    }

    /** @inheritDoc */
    public function end(?float $duration = null): void
    {
        // Since endSpanEx was not called directly it should not be kept in the stack trace
        $this->endSpanEx(/* numberOfStackFramesToSkip: */ 1, $duration);
    }
}
