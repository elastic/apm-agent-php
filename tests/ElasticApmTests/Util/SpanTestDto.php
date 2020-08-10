<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\Util;

use Elastic\Apm\SpanContextInterface;
use Elastic\Apm\SpanInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
class SpanTestDto extends ExecutionSegmentTestDto implements SpanInterface
{
    /** @var string|null */
    private $action = null;

    /** @var SpanContextTestDto */
    private $context;

    /** @var string */
    private $parentId;

    /** @var float */
    private $start;

    /** @var string|null */
    private $subtype = null;

    /** @var string */
    private $transactionId;

    public function __construct()
    {
        $this->context = new SpanContextTestDto();
    }

    public function setAction(?string $action): void
    {
        $this->action = $action;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function contextDto(): SpanContextTestDto
    {
        return $this->context;
    }

    public function context(): SpanContextInterface
    {
        return $this->context;
    }

    public function setParentId(string $parentId): void
    {
        $this->parentId = $parentId;
    }

    public function getParentId(): string
    {
        return $this->parentId;
    }

    public function setStart(float $start): void
    {
        $this->start = $start;
    }

    public function getStart(): float
    {
        return $this->start;
    }

    public function setTransactionId(string $transactionId): void
    {
        $this->transactionId = $transactionId;
    }

    public function getTransactionId(): string
    {
        return $this->transactionId;
    }

    public function setSubtype(?string $subtype): void
    {
        $this->subtype = $subtype;
    }

    public function getSubtype(): ?string
    {
        return $this->subtype;
    }
}
