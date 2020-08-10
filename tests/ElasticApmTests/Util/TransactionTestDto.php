<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\Util;

use Closure;
use Elastic\Apm\Impl\NoopSpan;
use Elastic\Apm\Impl\Span;
use Elastic\Apm\Impl\TransactionContext;
use Elastic\Apm\SpanInterface;
use Elastic\Apm\TransactionContextInterface;
use Elastic\Apm\TransactionInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
class TransactionTestDto extends ExecutionSegmentTestDto implements TransactionInterface
{
    /** @var TransactionContextTestDto */
    private $context;

    /** @var int */
    protected $droppedSpansCount = 0;

    /** @var string|null */
    private $parentId = null;

    /** @var int */
    private $startedSpansCount = 0;

    public function __construct()
    {
        $this->context = new TransactionContextTestDto();
    }

    public function contextDto(): TransactionContextTestDto
    {
        return $this->context;
    }

    public function context(): TransactionContextInterface
    {
        return $this->context;
    }

    public function setDroppedSpansCount(int $droppedSpansCount): void
    {
        $this->droppedSpansCount = $droppedSpansCount;
    }

    public function getDroppedSpansCount(): int
    {
        return $this->droppedSpansCount;
    }

    public function setStartedSpansCount(int $startedSpansCount): void
    {
        $this->startedSpansCount = $startedSpansCount;
    }

    public function getStartedSpansCount(): int
    {
        return $this->startedSpansCount;
    }

    public function setParentId(?string $parentId): void
    {
        $this->parentId = $parentId;
    }

    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    public function beginCurrentSpan(
        string $name,
        string $type,
        ?string $subtype = null,
        ?string $action = null,
        ?float $timestamp = null
    ): SpanInterface {
        throw self::buildUnsupportedMethodException(__FUNCTION__);
    }

    public function captureCurrentSpan(
        string $name,
        string $type,
        Closure $callback,
        ?string $subtype = null,
        ?string $action = null,
        ?float $timestamp = null
    ) {
        throw self::buildUnsupportedMethodException(__FUNCTION__);
    }

    public function getCurrentSpan(): SpanInterface
    {
        throw self::buildUnsupportedMethodException(__FUNCTION__);
    }
}
