<?php

declare(strict_types=1);

namespace Elastic\Apm;

interface SpanInterface extends ExecutionSegmentInterface, SpanDataInterface
{
    /**
     * @param string|null $subtype
     *
     * @see SpanDataInterface::getSubtype() For the description
     */
    public function setSubtype(?string $subtype): void;

    /**
     * @param string|null $action
     *
     * @see SpanDataInterface::getAction() For the description
     */
    public function setAction(?string $action): void;

    /**
     * Extended version of ExecutionSegmentInterface::end()
     *
     * @param float|null $duration                  In milliseconds with 3 decimal points.
     * @param int        $numberOfStackFramesToSkip Number of stack frames to skip when capturing stack trace.
     *
     * @see ExecutionSegmentInterface::end() For the description
     */
    public function endSpanEx(?float $duration = null, int $numberOfStackFramesToSkip = 0): void;
}
