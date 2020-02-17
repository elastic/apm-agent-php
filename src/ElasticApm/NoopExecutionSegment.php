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

    public function getId(): string
    {
        return self::ID;
    }

    public function setId(string $id): void
    {
    }

    public function getTraceId(): string
    {
        return self::TRACE_ID;
    }

    public function setTraceId(string $traceId): void
    {
    }

    public function getType(): string
    {
        return self::TYPE;
    }

    public function setType(string $type): void
    {
    }

    public function beginChildSpan(
        string $name,
        string $type,
        ?string $subtype = null,
        ?string $action = null
    ): SpanInterface {
        return NoopSpan::create();
    }

    public function end($endTime = null): void
    {
    }

    public function setTag(string $key, $value): void
    {
    }

    public function getTag(string $key, $default = null)
    {
        return $default;
    }
}
