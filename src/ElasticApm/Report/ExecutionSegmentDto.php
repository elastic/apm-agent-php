<?php

declare(strict_types=1);

namespace ElasticApm\Report;

abstract class ExecutionSegmentDto extends TimedEventDto implements ExecutionSegmentDtoInterface
{
    /** @var string */
    private $id;

    /** @var string */
    private $traceId;

    /** @var string */
    private $type;

    /** @inheritDoc */
    public function getId(): string
    {
        return $this->id;
    }

    /** @inheritDoc */
    public function setId(string $id): void
    {
        $this->id = $id;
    }

    /** @inheritDoc */
    public function getTraceId(): string
    {
        return $this->traceId;
    }

    /** @inheritDoc */
    public function setTraceId(string $traceId): void
    {
        $this->traceId = $traceId;
    }

    /** @inheritDoc */
    public function getType(): string
    {
        return $this->type;
    }

    /** @inheritDoc */
    public function setType(string $type): void
    {
        $this->type = $type;
    }
}
