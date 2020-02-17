<?php

declare(strict_types=1);

namespace ElasticApm\Impl;

use ElasticApm\Report\SpanDto;
use ElasticApm\SpanInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class Span extends SpanDto implements SpanInterface
{
    use ExecutionSegment;

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
        $this->constructExecutionSegment($containingTransaction->getTracer(), $type);

        $this->setName($name);
        $this->setSubtype($subtype);
        $this->setAction($action);

        $this->containingTransaction = $containingTransaction;
        $this->setTransactionId($containingTransaction->getId());
        $this->setTraceId($containingTransaction->getTraceId());

        $this->parentSpan = $parentSpan;
        $this->setParentId($parentSpan === null ? $containingTransaction->getId() : $parentSpan->getId());
    }

    public function beginChildSpan(
        string $name,
        string $type,
        ?string $subtype = null,
        ?string $action = null
    ): SpanInterface {
        return new Span($this->containingTransaction, /* parentSpan: */ $this, $name, $type, $subtype, $action);
    }

    public function end($endTime = null): void
    {
        $this->tracer->getReporter()->reportSpan($this);

        if ($this->containingTransaction->getCurrentSpan() === $this) {
            $this->containingTransaction->popCurrentSpan();
        }
    }

    public function getParentSpan(): ?Span
    {
        return $this->parentSpan;
    }
}
