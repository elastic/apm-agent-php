<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Util;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
class AssertException extends InternalFailureException
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }
}
