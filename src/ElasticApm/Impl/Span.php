<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Util\SerializationUtil;
use Elastic\Apm\Impl\Util\TimeUtil;
use Elastic\Apm\SpanContextInterface;
use Elastic\Apm\SpanInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class Span extends ExecutionSegment implements SpanInterface
{
    /** @var string|null */
    private $action = null;

    /** @var Transaction */
    private $containingTransaction;

    /** @var SpanContext|null */
    private $context = null;

    /** @var Logger */
    private $logger;

    /** @var string */
    private $parentId;

    /** @var Span|null */
    private $parentSpan;

    /** @var float */
    private $start;

    /** @var string|null */
    private $subtype = null;

    /** @var string */
    private $transactionId;

    public function __construct(
        Transaction $containingTransaction,
        ?Span $parentSpan,
        string $name,
        string $type,
        ?string $subtype = null,
        ?string $action = null,
        ?float $timestamp = null
    ) {
        parent::__construct(
            $containingTransaction->tracer,
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

    public function getStart(): float
    {
        return $this->start;
    }

    public function getParentSpan(): ?Span
    {
        return $this->parentSpan;
    }

    public function getParentId(): string
    {
        return $this->parentId;
    }

    public function getTransactionId(): string
    {
        return $this->transactionId;
    }

    public function setAction(?string $action): void
    {
        if ($this->checkIfAlreadyEnded(__FUNCTION__)) {
            return;
        }

        $this->action = Util\TextUtil::limitNullableKeywordString($action);
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setSubtype(?string $subtype): void
    {
        if ($this->checkIfAlreadyEnded(__FUNCTION__)) {
            return;
        }

        $this->subtype = Util\TextUtil::limitNullableKeywordString($subtype);
    }

    public function getSubtype(): ?string
    {
        return $this->subtype;
    }

    public function context(): SpanContextInterface
    {
        if (is_null($this->context)) {
            if ($this->checkIfAlreadyEnded(__FUNCTION__)) {
                return NoopSpanContext::singletonInstance();
            }
            $this->context = new SpanContext($this->tracer->loggerFactory());
        }

        return $this->context;
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

    public function end(?float $duration = null): void
    {
        if ($this->checkIfAlreadyEnded(__FUNCTION__)) {
            return;
        }

        parent::end($duration);

        $this->tracer->getEventSink()->consumeSpan($this);

        if ($this->containingTransaction->getCurrentSpan() === $this) {
            $this->containingTransaction->popCurrentSpan();
        }
    }

    /**
     * @return array<string, mixed>
     *
     * Called by json_encode
     *
     * @noinspection PhpUnused
     */
    public function jsonSerialize(): array
    {
        return SerializationUtil::buildJsonSerializeResultWithBase(
            parent::jsonSerialize(),
            [
                'action'         => $this->action,
                'context'        => SerializationUtil::nullIfEmpty($this->context),
                'parent_id'      => $this->parentId,
                'start'          => $this->start,
                'subtype'        => $this->subtype,
                'transaction_id' => $this->transactionId,
            ]
        );
    }
}
