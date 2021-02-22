<?php

declare(strict_types=1);

namespace Elastic\Apm;

use Elastic\Apm\Impl\HttpDistributedTracing;

final class DistributedTracingData
{
    /** @var string */
    public $traceId;

    /** @var string */
    public $parentId;

    /** @var bool */
    public $isSampled;

    public function serializeToString(): string
    {
        return HttpDistributedTracing::buildTraceParentHeader($this);
    }
}
