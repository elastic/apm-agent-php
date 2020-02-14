<?php

declare(strict_types=1);

namespace ElasticApm\Impl;

use ElasticApm\Report\ReporterInterface;
use ElasticApm\TracerInterface;
use ElasticApm\TransactionInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
class Tracer implements TracerInterface
{
    /** @var ReporterInterface */
    private $reporter;

    public function __construct(ReporterInterface $reporter)
    {
        $this->reporter = $reporter;
    }

    public function beginTransaction(?string $name, string $type): TransactionInterface
    {
        return new Transaction($this, $name, $type);
    }

    public function getReporter(): ReporterInterface
    {
        return $this->reporter;
    }

    public function isNoop(): bool
    {
        return false;
    }
}
