<?php

/** @noinspection PhpUndefinedClassInspection */

declare(strict_types=1);

namespace Elastic\Apm\AutoInstrument;

interface RegistrationContextInterface
{
    public function interceptCallsToInternalMethod(
        string $className,
        string $methodName,
        CallTrackerFactoryInterface $callTrackerFactory
    ): void;
}
