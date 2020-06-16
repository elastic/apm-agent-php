<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Util;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class NumericUtil
{
    use StaticClassTrait;

    /**
     * @param mixed $rangeFirst
     * @param mixed $x
     * @param mixed $rangeLast
     *
     * @return bool
     *
     * @template        T
     * @phpstan-param   T $rangeFirst
     * @phpstan-param   T $x
     * @phpstan-param   T $rangeLast
     *
     */
    public static function isInInclusiveRange($rangeFirst, $x, $rangeLast): bool
    {
        return ($rangeFirst <= $x) && ($x <= $rangeLast);
    }
}
