<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\AutoInstrument;

use Elastic\Apm\AutoInstrument\CallTrackerInterface;
use Elastic\Apm\SpanInterface;
use Throwable;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
class CallToSpanTracker implements CallTrackerInterface
{
    /** @var SpanInterface */
    public $span;

    /** @var null|callable(SpanInterface, mixed): mixed */
    private $onCallNormalEnd;

    /** @var null|callable(SpanInterface, Throwable): Throwable */
    private $onCallEndByException;

    /**
     * CallToSpanTracker constructor.
     *
     * @param SpanInterface $span
     * @param callable(SpanInterface, mixed): mixed          $onCallNormalEnd
     * @param callable(SpanInterface, Throwable): Throwable  $onCallEndByException
     */
    public function __construct(
        SpanInterface $span,
        ?callable $onCallNormalEnd,
        ?callable $onCallEndByException
    ) {
        $this->span = $span;
        $this->onCallNormalEnd = $onCallNormalEnd;
        $this->onCallEndByException = $onCallEndByException;
    }

    /**
     * @param mixed $returnValue Return value of the intercepted call
     *
     * @return mixed Value to return to the caller of the intercepted function
     */
    public function onCallNormalEnd($returnValue)
    {
        if (is_null($this->onCallNormalEnd)) {
            $this->span->end();
            return $returnValue;
        }
        return ($this->onCallNormalEnd)($this->span, $returnValue);
    }

    /**
     * @param Throwable $throwable Throwable propagated out of the intercepted call
     *
     * @return Throwable Throwable to propagate to the caller of the intercepted function
     */
    public function onCallEndByException(Throwable $throwable): Throwable
    {
        if (is_null($this->onCallEndByException)) {
            $this->span->end();
            return $throwable;
        }
        return ($this->onCallEndByException)($this->span, $throwable);
    }
}
