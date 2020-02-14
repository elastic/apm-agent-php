<?php

declare(strict_types=1);

namespace ElasticApm\Report;

class ReporterBuilder
{
    public static function startNew(): self
    {
        return new self();
    }

    public function build(): ReporterInterface
    {
        return NoopReporter::create();
    }
}
