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
     * @param mixed $intervalLeft
     * @param mixed $x
     * @param mixed $intervalRight
     *
     * @return bool
     *
     * @template        T
     * @phpstan-param   T $intervalLeft
     * @phpstan-param   T $x
     * @phpstan-param   T $intervalRight
     *
     */
    public static function isInClosedInterval($intervalLeft, $x, $intervalRight): bool
    {
        return ($intervalLeft <= $x) && ($x <= $intervalRight);
    }

    /**
     * @param mixed $intervalLeft
     * @param mixed $x
     * @param mixed $intervalRight
     *
     * @return bool
     *
     * @template        T
     * @phpstan-param   T $intervalLeft
     * @phpstan-param   T $x
     * @phpstan-param   T $intervalRight
     *
     */
    public static function isInOpenInterval($intervalLeft, $x, $intervalRight): bool
    {
        return ($intervalLeft < $x) && ($x < $intervalRight);
    }

    /**
     * @param mixed $intervalLeft
     * @param mixed $x
     * @param mixed $intervalRight
     *
     * @return bool
     *
     * @template        T
     * @phpstan-param   T $intervalLeft
     * @phpstan-param   T $x
     * @phpstan-param   T $intervalRight
     *
     */
    public static function isInRightOpenInterval($intervalLeft, $x, $intervalRight): bool
    {
        return ($intervalLeft <= $x) && ($x < $intervalRight);
    }

    /**
     * @param mixed $intervalLeft
     * @param mixed $x
     * @param mixed $intervalRight
     *
     * @return bool
     *
     * @template        T
     * @phpstan-param   T $intervalLeft
     * @phpstan-param   T $x
     * @phpstan-param   T $intervalRight
     *
     */
    public static function isInLeftOpenInterval($intervalLeft, $x, $intervalRight): bool
    {
        return ($intervalLeft < $x) && ($x <= $intervalRight);
    }
}
