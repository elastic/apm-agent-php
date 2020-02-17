<?php

declare(strict_types=1);

namespace ElasticApm\Report;

final class ReporterBuilder
{
    /**
     * Constructor is hidden because startNew() should be used instead.
     */
    private function __construct()
    {
    }

    public static function startNew(): self
    {
        return new self();
    }

    public function build(): ReporterInterface
    {
        return NoopReporter::create();
    }
}
