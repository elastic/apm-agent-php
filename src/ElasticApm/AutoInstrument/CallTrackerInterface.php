<?php

/** @noinspection PhpUndefinedClassInspection */

declare(strict_types=1);

namespace Elastic\Apm\AutoInstrument;

use Throwable;

interface CallTrackerInterface
{
    /**
     * @param mixed $returnValue Return value of the intercepted call
     *
     * @return mixed Value to return to the caller of the intercepted function
     */
    public function onCallNormalEnd($returnValue);

    /**
     * @param Throwable $throwable Throwable propagated out of the intercepted call
     *
     * @return Throwable Throwable to propagate to the caller of the intercepted function
     */
    public function onCallEndByException(Throwable $throwable): Throwable;
}
