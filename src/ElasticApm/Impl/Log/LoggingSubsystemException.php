<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Log;

use RuntimeException;
use Throwable;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class LoggingSubsystemException extends RuntimeException
{
    public function __construct(string $message, ?Throwable $causedBy = null, int $code = 0)
    {
        parent::__construct($message, $code, $causedBy);
    }
}
