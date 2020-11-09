<?php

declare(strict_types=1);

namespace ElasticApmTests\Util;

use RuntimeException;
use Throwable;

class DummyExceptionForTests extends RuntimeException
{
    public const NAMESPACE = __NAMESPACE__;
    public const CLASS_NAME = 'DummyExceptionForTests';

    public function __construct(string $message, int $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
