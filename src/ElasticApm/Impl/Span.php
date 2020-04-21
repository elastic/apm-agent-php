<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Elastic\Apm\Impl\Util\TimeUtil;
use Elastic\Apm\SpanInterface;

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

        $this->start = TimeUtil::calcDuration($containingTransaction->getTimestamp(), $this->getTimestamp());
    }

    /** @inheritDoc */
    public function beginChildSpan(
        string $name,
        string $type,
        ?string $subtype = null,
        ?string $action = null
    ): SpanInterface {
        return new Span($this->containingTransaction, /* parentSpan: */ $this, $name, $type, $subtype, $action);
    }

    /** @inheritDoc */
    public function end(?float $duration = null): void
    {
        if (!$this->endExecutionSegment($duration)) {
            return;
        }

        $this->getTracer()->getReporter()->reportSpan($this);

        if ($this->containingTransaction->getCurrentSpan() === $this) {
            $this->containingTransaction->popCurrentSpan();
        }
    }

    /** @inheritDoc */
    public function getTransactionId(): string
    {
        return $this->transactionId;
    }

    /** @inheritDoc */
    public function getParentId(): string
    {
        return $this->parentId;
    }

    /** @inheritDoc */
    public function getStart(): float
    {
        return $this->start;
    }

    /** @inheritDoc */
    public function getName(): string
    {
        return $this->name;
    }

    /** @inheritDoc */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /** @inheritDoc */
    public function getSubtype(): ?string
    {
        return $this->subtype;
    }

    /** @inheritDoc */
    public function setSubtype(?string $subtype): void
    {
        $this->subtype = $subtype;
    }

    /** @inheritDoc */
    public function getAction(): ?string
    {
        return $this->action;
    }

    /** @inheritDoc */
    public function setAction(?string $action): void
    {
        $this->action = $action;
    }

    /** @inheritDoc */
    public function getParentSpan(): ?Span
    {
        return $this->parentSpan;
    }
}
