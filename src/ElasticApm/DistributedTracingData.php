<?php

declare(strict_types=1);

namespace Elastic\Apm;

final class DistributedTracingData
{
    /** @var string */
    public $traceId;

    /** @var string */
    public $parentId;

    /** @var bool */
    public $isSampled;
}
