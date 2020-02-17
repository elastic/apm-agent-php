<?php

declare(strict_types=1);

namespace ElasticApm;

abstract class NoopExecutionSegment extends NoopTimedEvent implements ExecutionSegmentInterface
{
    /** @var string */
    public const ID = '0000000000000000';

    /** @var string */
    public const TRACE_ID = '00000000000000000000000000000000';

    /** @var string */
    public const TYPE = 'noop';

    /** @inheritDoc */
    public function getId(): string
    {
        return self::ID;
    }

    /** @inheritDoc */
    public function setId(string $id): void
    {
    }

    /** @inheritDoc */
    public function getTraceId(): string
    {
        return self::TRACE_ID;
    }

    /** @inheritDoc */
    public function setTraceId(string $traceId): void
    {
    }

    /** @inheritDoc */
    public function getType(): string
    {
        return self::TYPE;
    }

    /** @inheritDoc */
    public function setType(string $type): void
    {
    }

    /** @inheritDoc */
    public function beginChildSpan(
        string $name,
        string $type,
        ?string $subtype = null,
        ?string $action = null
    ): SpanInterface {
        return NoopSpan::create();
    }

    /** @inheritDoc */
    public function end($endTime = null): void
    {
    }
}
