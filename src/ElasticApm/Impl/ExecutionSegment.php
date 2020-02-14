<?php

declare(strict_types=1);

namespace ElasticApm\Impl;

/**
 * Code in this file is part implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
trait ExecutionSegment
{
    /** @var Tracer */
    private $tracer;

    public function constructExecutionSegment(Tracer $tracer, string $type): void
    {
        $this->tracer = $tracer;
        $this->setType($type);
    }

    /** @noinspection PhpUnused */
    public function isNoop(): bool
    {
        return false;
    }

    public function getTracer(): Tracer
    {
        return $this->tracer;
    }
}
