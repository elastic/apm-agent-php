<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\AutoInstrument;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class OnInterceptedCallEndWrapper
{
    /** @var int */
    public $funcToInterceptId;

    /** @var callable */
    public $wrappedOnInterceptedCallEndCallback;

    public function __construct(int $funcToInterceptId, callable $wrappedOnInterceptedCallEndCallback)
    {
        $this->funcToInterceptId = $funcToInterceptId;
        $this->wrappedOnInterceptedCallEndCallback = $wrappedOnInterceptedCallEndCallback;
    }
}
