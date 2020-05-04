<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Util;

use RuntimeException;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
class InternalFailureException extends RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }
}
