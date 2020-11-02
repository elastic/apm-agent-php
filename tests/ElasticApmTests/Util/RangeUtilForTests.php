<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\Util;

use Elastic\Apm\Impl\Util\StaticClassTrait;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class RangeUtilForTests
{
    use StaticClassTrait;

    /**
     * @param int $begin
     * @param int $end
     * @param int $step
     *
     * @return iterable<int>
     */
    public static function generate(int $begin, int $end, int $step = 1): iterable
    {
        for ($i = $begin; $i < $end; $i += $step) {
            yield $i;
        }
    }

    /**
     * @param int $count
     *
     * @return iterable<int>
     */
    public static function generateUpTo(int $count): iterable
    {
        return self::generate(0, $count);
    }

    /**
     * @param int $begin
     * @param int $end
     *
     * @return iterable<int>
     */
    public static function generateFromToIncluding(int $begin, int $end): iterable
    {
        return self::generate($begin, $end + 1);
    }
}
