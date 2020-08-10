<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Closure;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Util\IdGenerator;
use Elastic\Apm\Impl\Util\SerializationUtil;
use Elastic\Apm\SpanInterface;
use Elastic\Apm\TransactionContextInterface;
use Elastic\Apm\TransactionInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class Transaction extends ExecutionSegment implements TransactionInterface
{
    /** @var TransactionContext|null */
    private $context = null;

    /** @var Span|null */
    private $currentSpan = null;

    /** @var int */
    protected $droppedSpansCount = 0;

    /** @var Logger */
    private $logger;

    /** @var string|null */
    private $parentId = null;

    /** @var int */
    private $startedSpansCount = 0;

    public function __construct(Tracer $tracer, string $name, string $type, ?float $timestamp = null)
    {
        parent::__construct(
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

    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    public function context(): TransactionContextInterface
    {
        if (is_null($this->context)) {
            if ($this->checkIfAlreadyEnded(__FUNCTION__)) {
                return NoopTransactionContext::singletonInstance();
            }
            $this->context = new TransactionContext($this->tracer->loggerFactory());
        }

        return $this->context;
    }

    public function addStartedSpan(): void
    {
        if ($this->checkIfAlreadyEnded(__FUNCTION__)) {
            return;
        }

        ++$this->startedSpansCount;
    }

    public function getDroppedSpansCount(): int
    {
        return $this->droppedSpansCount;
    }

    public function getStartedSpansCount(): int
    {
        return $this->startedSpansCount;
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

    public function discard(): void
    {
        while (!is_null($this->currentSpan)) {
            if (!$this->currentSpan->hasEnded()) {
                $this->currentSpan->discard();
            }
            $this->popCurrentSpan();
        }

        parent::discard();
    }

    public function end(?float $duration = null): void
    {
        if ($this->checkIfAlreadyEnded(__FUNCTION__)) {
            return;
        }

        parent::end($duration);

        $this->tracer->getEventSink()->consumeTransaction($this);

        if ($this->tracer->getCurrentTransaction() === $this) {
            $this->tracer->resetCurrentTransaction();
        }
    }

    /**
     * @return array<string, mixed>
     *
     * Called by json_encode
     * @noinspection PhpUnused
     */
    public function jsonSerialize(): array
    {
        $spanCountSubObject = ['started' => $this->getStartedSpansCount()];
        if ($this->getDroppedSpansCount() != 0) {
            $spanCountSubObject['dropped'] = $this->getDroppedSpansCount();
        }

        return SerializationUtil::buildJsonSerializeResultWithBase(
            parent::jsonSerialize(),
            [
                'context'    => SerializationUtil::nullIfEmpty($this->context),
                'parent_id'  => $this->parentId,
                'span_count' => $spanCountSubObject,
            ]
        );
    }
}
