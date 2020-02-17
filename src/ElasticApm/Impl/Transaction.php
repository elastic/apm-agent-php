<?php

declare(strict_types=1);

namespace ElasticApm\Impl;

use ElasticApm\Impl\Util\IdGenerator;
use ElasticApm\NoopSpan;
use ElasticApm\Report\TransactionDto;
use ElasticApm\SpanInterface;
use ElasticApm\TransactionInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class Transaction extends TransactionDto implements TransactionInterface
{
    use ExecutionSegment;

    /** @var Span|null */
    private $currentSpan;

    public function __construct(Tracer $tracer, ?string $name, string $type)
    {
        $this->constructExecutionSegment($tracer, $type);

        $this->setName($name);
        $this->setTraceId(IdGenerator::generateId(Constants::TRACE_ID_SIZE_IN_BYTES));

        $this->currentSpan = null;
    }

    public function beginChildSpan(
        string $name,
        string $type,
        ?string $subtype = null,
        ?string $action = null
    ): SpanInterface {
        return $this->beginChildSpanImpl(/* parentSpan: */ null, $name, $type, $subtype, $action);
    }

    public function end($endTime = null): void
    {
        $this->tracer->getReporter()->reportTransaction($this);

        if ($this->tracer->getCurrentTransaction() === $this) {
            $this->tracer->resetCurrentTransaction();
        }
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

    private function beginChildSpanImpl(
        ?Span $parentSpan,
        string $name,
        string $type,
        ?string $subtype = null,
        ?string $action = null
    ): Span {
        return new Span(/* containingTransaction: */ $this, $parentSpan, $name, $type, $subtype, $action);
    }
}
