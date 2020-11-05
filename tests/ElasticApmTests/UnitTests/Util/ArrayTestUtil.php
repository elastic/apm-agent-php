<?php

declare(strict_types=1);

namespace ElasticApmTests\UnitTests\Util;

use Elastic\Apm\Impl\Util\StaticClassTrait;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class ArrayTestUtil
{
    use StaticClassTrait;

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
