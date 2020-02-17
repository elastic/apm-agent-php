<?php

declare(strict_types=1);

namespace ElasticApm;

use ElasticApm\Report\TransactionDtoInterface;

interface TransactionInterface extends ExecutionSegmentInterface, TransactionDtoInterface
{
    public function beginCurrentSpan(
        string $name,
        string $type,
        ?string $subtype = null,
        ?string $action = null
    ): SpanInterface;

    public function getCurrentSpan(): SpanInterface;
}
