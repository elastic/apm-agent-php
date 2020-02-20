<?php

declare(strict_types=1);

namespace ElasticApm\Impl\Util;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
trait NoopObjectTrait
{
    use CachedSingletonInstanceTrait;

    public function isNoop(): bool
    {
        return true;
    }
}
