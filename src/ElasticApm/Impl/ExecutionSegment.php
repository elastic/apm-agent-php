<?php

declare(strict_types=1);

namespace ElasticApm\Impl;

use ElasticApm\Impl\Util\IdGenerator;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
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
        $this->setId(IdGenerator::generateId(Constants::EXECUTION_SEGMENT_ID_SIZE_IN_BYTES));
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
