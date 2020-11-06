<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Elastic\Apm\Impl\Util\NoopObjectTrait;
use Elastic\Apm\SpanContextInterface;
use Elastic\Apm\SpanInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class NoopSpan extends NoopExecutionSegment implements SpanInterface
{
    use NoopObjectTrait;

    public function getTransactionId(): string
    {
        return NoopTransaction::ID;
    }

    public function getParentId(): string
    {
        return NoopTransaction::ID;
    }

    public function setSubtype(?string $subtype): void
    {
    }

    public function setAction(?string $action): void
    {
    }

    public function endSpanEx(int $numberOfStackFramesToSkip, ?float $duration = null): void
    {
    }

    public function context(): SpanContextInterface
    {
        return NoopSpanContext::singletonInstance();
    }
}
