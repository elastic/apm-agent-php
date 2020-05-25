<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests\Util;

use RuntimeException;
use Throwable;

final class WrappedAppCodeException extends RuntimeException
{
    /** @var Throwable */
    private $wrappedException;

    public function __construct(Throwable $wrappedException)
    {
        parent::__construct();
        $this->wrappedException = $wrappedException;
    }

    public function wrappedException(): Throwable
    {
        return$this->wrappedException;
    }
}
