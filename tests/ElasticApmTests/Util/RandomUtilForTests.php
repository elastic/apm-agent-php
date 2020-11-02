<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\Util;

use Elastic\Apm\Impl\Util\StaticClassTrait;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class RandomUtilForTests
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
        /** @noinspection PhpUnhandledExceptionInspection */
        return random_int($min, $max);
    }

    public static function generateInt(): int
    {
        return self::generateIntInRange(PHP_INT_MIN, PHP_INT_MAX);
    }

    private static function generateFloatInShortRange(float $min, float $max, bool $includeMax): float
    {
        $randRangeMax = $includeMax ? mt_getrandmax() : (mt_getrandmax() - 1);
        return $min + ((mt_rand(0, $randRangeMax) / mt_getrandmax()) * ($max - $min));
    }

    public static function generateFloatInRange(float $min, float $max, bool $includeMax = true): float
    {
        if (
            (0 <= $min) || ($max <= 0) || (((FloatLimits::MIN / 2) < $min) && ($max < (FloatLimits::MAX / 2)))
        ) {
            return self::generateFloatInShortRange($min, $max, $includeMax);
        }

        // Yes, it's definitely not a uniform for [$min, $max] range but it's only used in tests so...
        return self::generateBool()
            ? self::generateFloatInShortRange(0, $max, $includeMax)
            : self::generateFloatInShortRange($min, 0, /* includeMax */ false);
    }

    /**
     * @param array<mixed> $totalSet
     *
     * @param int          $subSetSize
     *
     * @return array<mixed>
     */
    public static function arrayRandValues(array $totalSet, int $subSetSize): array
    {
        if ($subSetSize > count($totalSet)) {
            throw new InvalidArgumentException(
                '$subSetSize should not be greater than $totalSet count.'
                . ' $totalSet count:' . count($totalSet) . '.'
                . ' $subSetSize:' . $subSetSize . '.'
            );
        }

        if ($subSetSize < 0) {
            throw new InvalidArgumentException(
                '$subSetSize should not be negative.'
                . ' $subSetSize:' . $subSetSize . '.'
            );
        }

        if ($subSetSize === 0) {
            return [];
        }

        $randSelectedSubsetIndexes = array_rand($totalSet, $subSetSize);
        if ($subSetSize === 1) {
            $randSelectedSubsetIndexes = [$randSelectedSubsetIndexes];
        }
        TestCase::assertIsArray($randSelectedSubsetIndexes);

        $randSelectedSubsetValues = [];
        foreach ($randSelectedSubsetIndexes as $index) {
            TestCase::assertIsInt($index);
            $randSelectedSubsetValues[] = $totalSet[$index];
        }
        return $randSelectedSubsetValues;
    }
}
