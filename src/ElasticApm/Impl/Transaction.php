<?php

declare(strict_types=1);

namespace ElasticApm\Impl;

use ElasticApm\Impl\Util\IdGenerator;
use ElasticApm\SpanInterface;
use ElasticApm\TransactionInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class Transaction extends ExecutionSegment implements TransactionInterface
{
    /** @var string|null */
    private $parentId;

    /** @var string|null */
    private $name;

    /** @var Span|null */
    private $currentSpan;

    public function __construct(Tracer $tracer, ?string $name, string $type)
    {
        parent::__construct($tracer, IdGenerator::generateId(IdGenerator::TRACE_ID_SIZE_IN_BYTES), $type);

        $this->setName($name);

        $this->currentSpan = null;
    }

    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function beginChildSpan(
        string $name,
        string $type,
        ?string $subtype = null,
        ?string $action = null
    ): SpanInterface {
        return $this->beginChildSpanImpl(/* parentSpan: */ null, $name, $type, $subtype, $action);
    }

    public function beginCurrentSpan(
        string $name,
        string $type,
        ?string $subtype = null,
        ?string $action = null
    ): SpanInterface {
        $this->currentSpan = $this->beginChildSpanImpl($this->currentSpan, $name, $type, $subtype, $action);
        return $this->currentSpan;
    }

    private function beginChildSpanImpl(
        ?Span $parentSpan,
        string $name,
        string $type,
        ?string $subtype = null,
        ?string $action = null
    ): Span {
        return new Span(/* containingTransaction: */ $this, $parentSpan, $name, $type, $subtype, $action);
    }

    public function getCurrentSpan(): SpanInterface
    {
        return $this->currentSpan ?? NoopSpan::create();
    }

    public function popCurrentSpan(): void
    {
        if ($this->currentSpan != null) {
            $this->currentSpan = $this->currentSpan->getParentSpan();
        }
    }

    public function end(?float $duration = null): void
    {
        parent::end($duration);

        $this->getTracer()->getReporter()->reportTransaction($this);

        if ($this->getTracer()->getCurrentTransaction() === $this) {
            $this->getTracer()->resetCurrentTransaction();
        }
    }
}
