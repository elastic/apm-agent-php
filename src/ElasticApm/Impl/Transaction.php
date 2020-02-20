<?php

/** @noinspection PhpUndefinedClassInspection */

declare(strict_types=1);

namespace ElasticApm\Impl;

use Closure;
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
        return new Span(
            $this /* <- containingTransaction*/,
            null /* <- parentSpan */,
            $name,
            $type,
            $subtype,
            $action
        );
    }

    public function beginCurrentSpan(
        string $name,
        string $type,
        ?string $subtype = null,
        ?string $action = null
    ): SpanInterface {
        $this->currentSpan = new Span(
            $this /* <- containingTransaction */,
            $this->currentSpan /* <- parentSpan */,
            $name,
            $type,
            $subtype,
            $action
        );
        return $this->currentSpan;
    }

    /** @inheritDoc */
    public function captureCurrentSpan(
        string $name,
        string $type,
        Closure $callback,
        ?string $subtype = null,
        ?string $action = null
    ) {
        $newSpan = $this->beginCurrentSpan($name, $type, $subtype, $action);
        try {
            return $callback($newSpan);
        } finally {
            $newSpan->end();
        }
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
        if (!$this->endExecutionSegment($duration)) {
            return;
        }

        $this->getTracer()->getReporter()->reportTransaction($this);

        if ($this->getTracer()->getCurrentTransaction() === $this) {
            $this->getTracer()->resetCurrentTransaction();
        }
    }
}
