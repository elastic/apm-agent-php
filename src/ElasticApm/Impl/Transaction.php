<?php

/** @noinspection PhpUndefinedClassInspection */

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Closure;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Util\IdGenerator;
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

    /** @var Span|null */
    private $currentSpan = null;

    /** @var Logger */
    private $logger;

    public function __construct(Tracer $tracer, string $name, string $type, ?float $timestamp = null)
    {
        $this->constructExecutionSegmentTrait(
            $tracer,
            IdGenerator::generateId(IdGenerator::TRACE_ID_SIZE_IN_BYTES),
            $name,
            $type,
            $timestamp
        );

        $this->setName($name);

        $this->logger = $this->createLogger(__NAMESPACE__, __CLASS__, __FILE__);

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Transaction created', ['parentId' => $this->parentId]);
    }

    public function addStartedSpan(): void
    {
        if ($this->checkIfAlreadyEnded(__FUNCTION__)) {
            return;
        }

        ++$this->startedSpansCount;
    }

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
            $this /* <- containingTransaction*/,
            null /* <- parentSpan */,
            $name,
            $type,
            $subtype,
            $action,
            $timestamp
        );
    }

    public function beginCurrentSpan(
        string $name,
        string $type,
        ?string $subtype = null,
        ?string $action = null,
        ?float $timestamp = null
    ): SpanInterface {
        if ($this->checkIfAlreadyEnded(__FUNCTION__) || !$this->tracer->isRecording()) {
            return NoopSpan::singletonInstance();
        }

        $this->currentSpan = new Span(
            $this /* <- containingTransaction */,
            $this->currentSpan /* <- parentSpan */,
            $name,
            $type,
            $subtype,
            $action,
            $timestamp
        );
        return $this->currentSpan;
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
        } finally {
            $newSpan->end();
        }
    }

    public function getCurrentSpan(): SpanInterface
    {
        return $this->currentSpan ?? NoopSpan::singletonInstance();
    }

    public function popCurrentSpan(): void
    {
        if ($this->currentSpan != null) {
            $this->currentSpan = $this->currentSpan->getParentSpan();
        }
    }

    public function end(?float $duration = null): void
    {
        if (!$this->endExecutionSegment($duration)) {
            return;
        }

        $this->getTracer()->getEventSink()->consumeTransactionData($this);

        if ($this->getTracer()->getCurrentTransaction() === $this) {
            $this->getTracer()->resetCurrentTransaction();
        }
    }

    public function discard(): void
    {
        while (!is_null($this->currentSpan)) {
            if (!$this->currentSpan->hasEnded()) {
                $this->currentSpan->discard();
            }
            $this->popCurrentSpan();
        }
        $this->discardExecutionSegment();
    }
}
