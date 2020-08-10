<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Closure;
use Elastic\Apm\ExecutionSegmentInterface;
use Elastic\Apm\Impl\Util\DbgUtil;
use Elastic\Apm\SpanInterface;

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
    public const NAME = 'NO-OP';

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

    public function getName(): string
    {
        return self::NAME;
    }

    public function setName(string $name): void
    {
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
        ?string $action = null,
        ?float $timestamp = null
    ): SpanInterface {
        return NoopSpan::singletonInstance();
    }

    public function captureChildSpan(
        string $name,
        string $type,
        Closure $callback,
        ?string $subtype = null,
        ?string $action = null,
        ?float $timestamp = null
    ) {
        return $callback(NoopSpan::singletonInstance());
    }

    public function end(?float $duration = null): void
    {
    }

    public function hasEnded(): bool
    {
        return true;
    }

    public function discard(): void
    {
    }

    public function __toString(): string
    {
        return DbgUtil::fqToShortClassName(get_class());
    }
}
