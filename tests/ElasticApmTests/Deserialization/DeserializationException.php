<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\Deserialization;

use RuntimeException;
use Throwable;

class DeserializationException extends RuntimeException
{
    public function __construct(string $message, int $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
