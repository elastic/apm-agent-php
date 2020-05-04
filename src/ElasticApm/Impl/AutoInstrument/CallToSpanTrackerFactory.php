<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\AutoInstrument;

use Elastic\Apm\AutoInstrument\CallTrackerFactoryInterface;
use Elastic\Apm\AutoInstrument\CallTrackerInterface;
use Elastic\Apm\AutoInstrument\RegistrationContextInterface;
use Elastic\Apm\SpanInterface;
use Throwable;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
class CallToSpanTrackerFactory implements CallTrackerFactoryInterface
{
    /** @var callable(mixed...): ?SpanInterface */
    private $onCallBegin;

    /** @var null|callable(SpanInterface, mixed): mixed */
    private $onCallNormalEnd;

    /** @var null|callable(SpanInterface, Throwable): Throwable */
    private $onCallEndByException;

    /**
     * @param callable(mixed...): ?SpanInterface $onCallBegin
     * @param null|callable(SpanInterface, mixed): mixed $onCallNormalEnd
     * @param null|callable(SpanInterface, Throwable): Throwable $onCallEndByException
     */
    public function __construct(
        callable $onCallBegin,
        ?callable $onCallNormalEnd = null,
        ?callable $onCallEndByException = null
    ) {
        $this->onCallBegin = $onCallBegin;
        $this->onCallNormalEnd = $onCallNormalEnd;
        $this->onCallEndByException = $onCallEndByException;
    }

    /**
     * @param mixed ...$interceptedCallArgs
     *
     * @return null|CallTrackerInterface
     */
    public function onCallBegin(...$interceptedCallArgs): ?CallTrackerInterface
    {
        $span = ($this->onCallBegin)(...$interceptedCallArgs);
        if (is_null($span)) {
            return null;
        }

        return new CallToSpanTracker(
            $span,
            $this->onCallNormalEnd,
            $this->onCallEndByException
        );
    }
}
