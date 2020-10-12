<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\Util;

use Elastic\Apm\Impl\Util\StaticClassTrait;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class TestRandomUtil
{
    use StaticClassTrait;

    public static function generateBool(): bool
    {
        return mt_rand(0, 1) !== 0;
    }

    public static function generateIntInRange(int $min, int $max): int
    {
        // This is used only in tests it's okay to use slower random generator
        // because it implements exactly what we need
        // even though we don't really need its cryptographically secure characteristics
        return random_int($min, $max);
    }

    public static function generateInt(): int
    {
        return self::generateIntInRange(PHP_INT_MIN, PHP_INT_MAX);
    }

    public static function generateFloatInRange(float $min, float $max, bool $includeMax = true): float
    {
        $randRangeMax = $includeMax ? mt_getrandmax() : (mt_getrandmax() - 1);
        return $min + ((mt_rand(0, $randRangeMax) / mt_getrandmax()) * ($max - $min));
    }
}
