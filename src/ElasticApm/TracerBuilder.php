<?php

declare(strict_types=1);

namespace ElasticApm;

use ElasticApm\Impl\Tracer;
use ElasticApm\Report\ReporterBuilder;
use ElasticApm\Report\ReporterInterface;

final class TracerBuilder
{
    /** @var bool */
    private $isEnabled = true;

    /** @var ReporterInterface|null */
    private $reporter;

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

    public function withEnabled(bool $isEnabled): self
    {
        $this->isEnabled = $isEnabled;
        return $this;
    }

    public function withReporter(ReporterInterface $reporter): self
    {
        $this->reporter = $reporter;
        return $this;
    }

    public function build(): TracerInterface
    {
        if (!$this->isEnabled) {
            return NoopTracer::create();
        }

        $reporter = $this->reporter ?? ReporterBuilder::startNew()->build();

        return new Tracer($reporter);
    }
}
