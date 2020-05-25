<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Util;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class ArrayUtil
{
    use StaticClassTrait;

    /**
     * @param string       $key
     * @param array<mixed> $array
     * @param mixed        $valueDst
     *
     * @return bool
     *
     * @template        T
     * @phpstan-param   T[] $array
     * @phpstan-param   T $valueDst
     */
    public static function getValueIfKeyExists(string $key, array $array, &$valueDst): bool
    {
        if (!array_key_exists($key, $array)) {
            return false;
        }

        $valueDst = $array[$key];
        return true;
    }

    /**
     * @param string|int   $key
     * @param array<mixed> $array
     * @param mixed        $fallbackValue
     *
     * @return mixed
     *
     * @template        T
     * @phpstan-param   T[] $array
     * @phpstan-param   T $fallbackValue
     * @phpstan-return  T
     */
    public static function getValueIfKeyExistsElse($key, array $array, $fallbackValue)
    {
        if (!array_key_exists($key, $array)) {
            return $fallbackValue;
        }

        return $array[$key];
    }
}
