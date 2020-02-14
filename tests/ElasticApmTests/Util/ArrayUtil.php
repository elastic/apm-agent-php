<?php

declare(strict_types=1);

namespace ElasticApmTests\Util;

/**
 * Code in this file is part implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
abstract class ArrayUtil
{
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
}
