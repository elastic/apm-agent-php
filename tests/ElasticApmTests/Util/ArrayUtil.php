<?php

declare(strict_types=1);

namespace ElasticApmTests\Util;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class ArrayUtil
{
    /**
     * Constructor is hidden because it's a "static" class
     */
    private function __construct()
    {
    }

    /**
     * @template       T
     * @phpstan-param  array<T> $haystack
     * @phpstan-param  callable $predicate
     *
     * @param array    $haystack
     * @param callable $predicate
     *
     * @return int Index of the first element for which $predicate returns true,
     * if no such element found then -1 is returned
     */
    public static function findIndexByPredicate(array $haystack, callable $predicate): int
    {
        $haystackCount = count($haystack);
        for ($i = 0; $i < $haystackCount; ++$i) {
            if ($predicate($haystack[$i])) {
                return $i;
            }
        }

        return -1;
    }

    /**
     * @template        T
     * @phpstan-param   iterable<T> $haystack
     * @phpstan-param   callable $predicate
     * @phpstan-param   T $default
     * @phpstan-return  T|null
     *
     * @param iterable $haystack
     * @param callable $predicate
     * @param null     $default
     *
     * @return mixed
     */
    public static function findByPredicate(iterable $haystack, callable $predicate, $default = null)
    {
        foreach ($haystack as $value) {
            if ($predicate($value)) {
                return $value;
            }
        }

        return $default;
    }
}
