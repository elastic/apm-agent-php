<?php

/** @noinspection PhpUndefinedClassInspection */

declare(strict_types=1);

namespace Elastic\Apm\AutoInstrument;

interface CallTrackerFactoryInterface
{
    /**
     * @param mixed ...$interceptedCallArgs
     *
     * @return null|CallTrackerInterface
     */
    public function onCallBegin(...$interceptedCallArgs): ?CallTrackerInterface;
}
