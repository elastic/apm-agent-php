<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Util;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class RandomUtil
{
    use StaticClassTrait;

    public static function generate01Float(): float
    {
        return mt_rand(0, mt_getrandmax()) / mt_getrandmax();
    }
}
