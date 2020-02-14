<?php

declare(strict_types=1);

namespace ElasticApm\Impl;

use ElasticApm\Report\TransactionDto;
use ElasticApm\SpanInterface;
use ElasticApm\TransactionInterface;

/**
 * Code in this file is part implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
class Transaction extends TransactionDto implements TransactionInterface
{
    use ExecutionSegment;

    public function __construct(Tracer $tracer, ?string $name, string $type)
    {
        $this->constructExecutionSegment($tracer, $type);
        $this->setName($name);
    }

    public function beginChildSpan(
        string $name,
        string $type,
        ?string $subtype = null,
        ?string $action = null
    ): SpanInterface {
        return new Span(/* containingTransaction: */ $this, /* parentSpan: */ null, $name, $type, $subtype, $action);
    }

    public function end($endTime = null): void
    {
        $this->tracer->getReporter()->reportTransaction($this);
    }
}
