<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\Util;

use Closure;
use Elastic\Apm\ExecutionSegmentInterface;
use Elastic\Apm\Impl\Util\DbgUtil;
use Elastic\Apm\Impl\Util\ObjectToStringBuilder;
use Elastic\Apm\SpanInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
abstract class ExecutionSegmentTestDto extends TestDtoBase implements ExecutionSegmentInterface
{
    /** @var float */
    private $duration;

    /** @var string */
    private $id;

    /** @var string */
    private $name;

    /** @var float UTC based and in microseconds since Unix epoch */
    private $timestamp;

    /** @var string */
    private $traceId;

    /** @var string */
    private $type;

    public function setDuration(float $duration): void
    {
        $this->duration = $duration;
    }

    public function getDuration(): float
    {
        return $this->duration;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setTimestamp(float $timestamp): void
    {
        $this->timestamp = $timestamp;
    }

    public function getTimestamp(): float
    {
        return $this->timestamp;
    }

    public function setTraceId(string $traceId): void
    {
        $this->traceId = $traceId;
    }

    public function getTraceId(): string
    {
        return $this->traceId;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function isNoop(): bool
    {
        return false;
    }

    public function hasEnded(): bool
    {
        return true;
    }

    public function beginChildSpan(
        string $name,
        string $type,
        ?string $subtype = null,
        ?string $action = null,
        ?float $timestamp = null
    ): SpanInterface {
        throw self::buildUnsupportedMethodException(__FUNCTION__);
    }

    public function captureChildSpan(
        string $name,
        string $type,
        Closure $callback,
        ?string $subtype = null,
        ?string $action = null,
        ?float $timestamp = null
    ) {
        throw self::buildUnsupportedMethodException(__FUNCTION__);
    }

    public function discard(): void
    {
        throw self::buildUnsupportedMethodException(__FUNCTION__);
    }

    public function end(?float $duration = null): void
    {
        throw self::buildUnsupportedMethodException(__FUNCTION__);
    }

    public function __toString(): string
    {
        $builder = new ObjectToStringBuilder(DbgUtil::fqToShortClassName(get_class()));
        $builder->add('ID', $this->id);
        $builder->add('name', $this->name);
        return $builder->build();
    }
}
