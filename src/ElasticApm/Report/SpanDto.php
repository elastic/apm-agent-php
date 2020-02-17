<?php

declare(strict_types=1);

namespace ElasticApm\Report;

class SpanDto extends ExecutionSegmentDto implements SpanDtoInterface
{
    /** @var string */
    private $transactionId;

    /** @var string */
    private $parentId;

    /** @var int|null */
    private $start;

    /** @var string */
    private $name;

    /** @var string|null */
    private $subtype;

    /** @var string|null */
    private $action;

    /** @inheritDoc */
    public function getTransactionId(): string
    {
        return $this->transactionId;
    }

    /** @inheritDoc */
    public function setTransactionId(string $transactionId): void
    {
        $this->transactionId = $transactionId;
    }

    /** @inheritDoc */
    public function getParentId(): string
    {
        return $this->parentId;
    }

    /** @inheritDoc */
    public function setParentId(string $parentId): void
    {
        $this->parentId = $parentId;
    }

    /** @inheritDoc */
    public function getStart(): ?int
    {
        return $this->start;
    }

    /** @inheritDoc */
    public function setStart(?int $start): void
    {
        $this->start = $start;
    }

    /** @inheritDoc */
    public function getName(): string
    {
        return $this->name;
    }

    /** @inheritDoc */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /** @inheritDoc */
    public function getSubtype(): ?string
    {
        return $this->subtype;
    }

    /** @inheritDoc */
    public function setSubtype(?string $subtype): void
    {
        $this->subtype = $subtype;
    }

    /** @inheritDoc */
    public function getAction(): ?string
    {
        return $this->action;
    }

    /** @inheritDoc */
    public function setAction(?string $action): void
    {
        $this->action = $action;
    }
}
