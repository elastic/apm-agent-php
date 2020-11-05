<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\BackendComm;

use RuntimeException;
use Throwable;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
class SerializationException extends RuntimeException
{
    public function __construct(string $message, ?Throwable $causedBy = null)
    {
        parent::__construct($message, /* code: */ 0, $causedBy);
    }
}
