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

    /** @inheritDoc */
    public function getParentId(): ?string
    {
        return null;
    }

    public function setParentId(?string $parentId): void
    {
    }

    /** @inheritDoc */
    public function getName(): ?string
    {
        return null;
    }

    /** @inheritDoc */
    public function setName(?string $name): void
    {
    }
}
