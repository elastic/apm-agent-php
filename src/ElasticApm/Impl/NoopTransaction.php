<?php

/** @noinspection PhpUndefinedClassInspection */

declare(strict_types=1);

namespace ElasticApm\Impl;

use Closure;
use ElasticApm\Impl\Util\NoopObjectTrait;
use ElasticApm\SpanInterface;
use ElasticApm\TransactionInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
class NoopTransaction extends NoopExecutionSegment implements TransactionInterface
{
    use NoopObjectTrait;

    /** @inheritDoc */
    public function getParentId(): ?string
    {
        return null;
    }

    /** @inheritDoc */
    public function getName(): ?string
    {
        return null;
    }

    /** @inheritDoc */
    public function setName(?string $name): void
    {
    }

    /** @inheritDoc */
    public function beginCurrentSpan(
        string $name,
        string $type,
        ?string $subtype = null,
        ?string $action = null
    ): SpanInterface {
        return NoopSpan::create();
    }

    /** @inheritDoc */
    public function captureCurrentSpan(
        string $name,
        string $type,
        Closure $callback,
        ?string $subtype = null,
        ?string $action = null
    ) {
        return $callback(NoopSpan::create());
    }

    /** @inheritDoc */
    public function getCurrentSpan(): SpanInterface
    {
        return NoopSpan::create();
    }
}
