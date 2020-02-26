<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Elastic\Apm\SpanInterface;
use Elastic\Apm\TransactionInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
interface ReporterInterface
{
    public function reportTransaction(TransactionInterface $transaction): void;

    public function reportSpan(SpanInterface $span): void;
}
