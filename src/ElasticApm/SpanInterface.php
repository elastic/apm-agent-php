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
     * @param int        $numberOfStackFramesToSkip Number of stack frames to skip when capturing stack trace.
     * @param float|null $duration                  In milliseconds with 3 decimal points.
     *
     * @see ExecutionSegmentInterface::end() For the description
     */
    public function endSpanEx(int $numberOfStackFramesToSkip, ?float $duration = null): void;
}
