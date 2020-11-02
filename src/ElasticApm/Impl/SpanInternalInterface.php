<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Elastic\Apm\SpanInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
interface SpanInternalInterface extends SpanInterface
{
    public function containingTransaction(): ?Transaction;

    public function parentSpan(): ?SpanInternalInterface;
}
