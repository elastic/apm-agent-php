<?php

declare(strict_types=1);

namespace ElasticApm\Report;

use ElasticApm\Impl\Util\NoopObjectTrait;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
class NoopReporter implements ReporterInterface
{
    use NoopObjectTrait;

    /**
     * Constructor is hidden because create() should be used instead.
     */
    private function __construct()
    {
    }

    /** @inheritDoc */
    public function reportTransaction(TransactionDtoInterface $transactionDto): void
    {
    }

    /** @inheritDoc */
    public function reportSpan(SpanDtoInterface $spanDto): void
    {
    }
}
