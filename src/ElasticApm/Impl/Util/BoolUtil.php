<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Util;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class BoolUtil
{
    use StaticClassTrait;

    public static function ifThen(bool $ifCond, bool $thenCond): bool
    {
        return $ifCond ? $thenCond : true;
    }
}
