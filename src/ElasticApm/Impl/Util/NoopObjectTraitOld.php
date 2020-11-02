<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Util;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
trait NoopObjectTraitOld
{
    public function isNoop(): bool
    {
        return true;
    }

    public function __toString(): string
    {
        return DbgUtil::fqToShortClassName(get_class($this));
    }
}
