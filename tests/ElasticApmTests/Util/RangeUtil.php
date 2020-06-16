<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\Util;

use Elastic\Apm\Impl\Util\StaticClassTrait;
use LogicException;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class RangeUtil
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
        if ($begin <= $end) {
            if ($step <= 0) {
                throw new LogicException('Step must be positive');
            }

            for ($i = $begin; $i < $end; $i += $step) {
                yield $i;
            }
        } else {
            if ($step >= 0) {
                throw new LogicException('Step must be negative');
            }

            for ($i = $begin; $i > $end; $i += $step) {
                yield $i;
            }
        }
    }
}
