<?php

declare(strict_types=1);

namespace ElasticApm\Impl;

use ElasticApm\ExecutionSegmentInterface;
use ElasticApm\SpanInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
abstract class NoopExecutionSegment implements ExecutionSegmentInterface
{
    /** @var string */
    public const ID = '0000000000000000';

    /** @var string */
    public const TRACE_ID = '00000000000000000000000000000000';

    /** @var string */
    public const TYPE = 'noop';

    public function getTimestamp(): float
    {
        return 0.0;
    }

    public function getDuration(): float
    {
        return 0.0;
    }

    public function getId(): string
    {
        return self::ID;
    }

    public function getTraceId(): string
    {
        return self::TRACE_ID;
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

    public function end(?float $duration = null): void
    {
    }

    public function setLabel(string $key, $value): void
    {
    }

    public function getLabel(string $key, $default = null)
    {
        return $default;
    }
}
