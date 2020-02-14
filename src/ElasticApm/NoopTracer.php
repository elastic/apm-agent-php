<?php

declare(strict_types=1);

namespace ElasticApm;

use ElasticApm\Impl\Util\NoopObjectTrait;

class NoopTracer implements TracerInterface
{
    use NoopObjectTrait;

    /**
     * Constructor is hidden because create() should be used instead.
     */
    private function __construct()
    {
    }

    public function beginTransaction(?string $name, string $type): TransactionInterface
    {
        return NoopTransaction::create();
    }
}
