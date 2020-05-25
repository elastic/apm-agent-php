<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Util\TimeUtil;
use Elastic\Apm\SpanInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class Span extends SpanData implements SpanInterface
{
    use ExecutionSegmentTrait;

    /** @var Transaction */
    private $containingTransaction;

    /** @var Span|null */
    private $parentSpan;

    /** @var Logger */
    private $logger;

    public function __construct(
        Transaction $containingTransaction,
        ?Span $parentSpan,
        string $name,
        string $type,
        ?string $subtype = null,
        ?string $action = null,
        ?float $timestamp = null
    ) {
        $this->constructExecutionSegmentTrait(
            $containingTransaction->getTracer(),
            $containingTransaction->getTraceId(),
            $name,
            $type,
            $timestamp
        );

        $this->setSubtype($subtype);
        $this->setAction($action);

        $this->containingTransaction = $containingTransaction;
        $this->transactionId = $containingTransaction->getId();

        $this->parentSpan = $parentSpan;
        $this->parentId = $parentSpan === null ? $containingTransaction->getId() : $parentSpan->getId();

        $this->start = TimeUtil::calcDuration($containingTransaction->getTimestamp(), $this->getTimestamp());

        $this->logger = $this->createLogger(__NAMESPACE__, __CLASS__, __FILE__);

        $containingTransaction->addStartedSpan();

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Span created', ['parentId' => $this->parentId]);
    }

    /** @inheritDoc */
    public function beginChildSpan(
        string $name,
        string $type,
        ?string $subtype = null,
        ?string $action = null,
        ?float $timestamp = null
    ): SpanInterface {
        if ($this->checkIfAlreadyEnded(__FUNCTION__) || !$this->tracer->isRecording()) {
            return NoopSpan::singletonInstance();
        }

        return new Span(
            $this->containingTransaction,
            /* parentSpan: */ $this,
            $name,
            $type,
            $subtype,
            $action,
            $timestamp
        );
    }

    /** @inheritDoc */
    public function end(?float $duration = null): void
    {
        if (!$this->endExecutionSegment($duration)) {
            return;
        }

        $this->getTracer()->getEventSink()->consumeSpanData($this);

        if ($this->containingTransaction->getCurrentSpan() === $this) {
            $this->containingTransaction->popCurrentSpan();
        }
    }

    /** @inheritDoc */
    public function setAction(?string $action): void
    {
        if ($this->checkIfAlreadyEnded(__FUNCTION__)) {
            return;
        }

        $this->action = $this->tracer->limitNullableKeywordString($action);
    }

    /** @inheritDoc */
    public function setSubtype(?string $subtype): void
    {
        if ($this->checkIfAlreadyEnded(__FUNCTION__)) {
            return;
        }

        $this->subtype = $this->tracer->limitNullableKeywordString($subtype);
    }

    public function getParentSpan(): ?Span
    {
        return $this->parentSpan;
    }
}
