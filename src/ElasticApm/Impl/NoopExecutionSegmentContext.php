<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl;

use Elastic\Apm\ExecutionSegmentContextInterface;
use Elastic\Apm\Impl\Log\LoggableInterface;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
abstract class NoopExecutionSegmentContext implements ExecutionSegmentContextInterface, LoggableInterface
{
    /** @inheritDoc */
    public function setLabel(string $key, $value): void
    {
    }
}
