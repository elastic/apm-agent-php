<?php

declare(strict_types=1);

namespace ElasticApm\Impl;

use ElasticApm\Impl\Util\NoopObjectTrait;
use ElasticApm\SpanInterface;
use ElasticApm\TransactionInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
class NoopTransaction extends NoopExecutionSegment implements TransactionInterface
{
    use NoopObjectTrait;

    public function getParentId(): ?string
    {
        return null;
    }

    public function getName(): ?string
    {
        return null;
    }

    public function setName(?string $name): void
    {
    }

    public function beginCurrentSpan(
        string $name,
        string $type,
        ?string $subtype = null,
        ?string $action = null
    ): SpanInterface {
        return NoopSpan::create();
    }

    public function getCurrentSpan(): SpanInterface
    {
        return NoopSpan::create();
    }
}
