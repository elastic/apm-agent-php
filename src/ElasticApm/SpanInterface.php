<?php

declare(strict_types=1);

namespace ElasticApm;

use ElasticApm\Report\SpanDtoInterface;

interface SpanInterface extends ExecutionSegmentInterface, SpanDtoInterface
{
}
