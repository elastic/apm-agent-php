<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Log;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
interface LoggableInterface
{
    /**
     * Used for logging subsystem to generate represantation for this object
     *
     * @param LogStreamInterface $stream
     */
    public function toLog(LogStreamInterface $stream): void;
}
