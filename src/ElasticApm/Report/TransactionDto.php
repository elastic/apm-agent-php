<?php

declare(strict_types=1);

namespace ElasticApm\Report;

class TransactionDto extends ExecutionSegmentDto implements TransactionDtoInterface
{
    /** @var string|null */
    private $parentId;

    /** @var string|null */
    private $name;

    /** @inheritDoc */
    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    /** @inheritDoc */
    public function setParentId(?string $parentId): void
    {
        $this->parentId = $parentId;
    }

    /** @inheritDoc */
    public function getName(): ?string
    {
        return $this->name;
    }

    /** @inheritDoc */
    public function setName(?string $name): void
    {
        $this->name = $name;
    }
}
