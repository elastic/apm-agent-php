<?php

declare(strict_types=1);

namespace ElasticApm\Impl;

use ElasticApm\Impl\Util\TimeUtil;
use ElasticApm\SpanInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class Span extends ExecutionSegment implements SpanInterface
{
    /** @var string */
    private $transactionId;

    /** @var string */
    private $parentId;

    /** @var float */
    private $start;

    /** @var string */
    private $name;

    /** @var string|null */
    private $subtype;

    /** @var string|null */
    private $action;

    /** @var Transaction */
    private $containingTransaction;

    /** @var Span|null */
    private $parentSpan;

    public function __construct(
        Transaction $containingTransaction,
        ?Span $parentSpan,
        string $name,
        string $type,
        ?string $subtype = null,
        ?string $action = null
    ) {
        parent::__construct($containingTransaction->getTracer(), $containingTransaction->getTraceId(), $type);

        $this->setName($name);
        $this->setSubtype($subtype);
        $this->setAction($action);

        $this->containingTransaction = $containingTransaction;
        $this->transactionId = $containingTransaction->getId();

        $this->parentSpan = $parentSpan;
        $this->parentId = $parentSpan === null ? $containingTransaction->getId() : $parentSpan->getId();

        $this->start = TimeUtil::calcDuration(
            $containingTransaction->getMonotonicBeginTime(),
            $this->getMonotonicBeginTime()
        );
    }

    public function beginChildSpan(
        string $name,
        string $type,
        ?string $subtype = null,
        ?string $action = null
    ): SpanInterface {
        return new Span($this->containingTransaction, /* parentSpan: */ $this, $name, $type, $subtype, $action);
    }

    public function end(?float $duration = null): void
    {
        parent::end($duration);

        $this->getTracer()->getReporter()->reportSpan($this);

        if ($this->containingTransaction->getCurrentSpan() === $this) {
            $this->containingTransaction->popCurrentSpan();
        }
    }

    public function getTransactionId(): string
    {
        return $this->transactionId;
    }

    public function getParentId(): string
    {
        return $this->parentId;
    }

    public function getStart(): float
    {
        return $this->start;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getSubtype(): ?string
    {
        return $this->subtype;
    }

    public function setSubtype(?string $subtype): void
    {
        $this->subtype = $subtype;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(?string $action): void
    {
        $this->action = $action;
    }

    public function getParentSpan(): ?Span
    {
        return $this->parentSpan;
    }
}
