<?php

declare(strict_types=1);

namespace ElasticApm;

use ElasticApm\Impl\Util\NoopObjectTrait;

class NoopTransaction extends NoopExecutionSegment implements TransactionInterface
{
    use NoopObjectTrait;

    /**
     * Constructor is hidden because create() should be used instead.
     */
    private function __construct()
    {
    }

    public function getParentId(): ?string
    {
        return null;
    }

    public function setParentId(?string $parentId): void
    {
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
