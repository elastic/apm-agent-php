<?php

declare(strict_types=1);

namespace ElasticApmTests\Util;

use Elastic\Apm\Impl\Util\StaticClassTrait;
use LogicException;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class IterableUtilForTests
{
    use StaticClassTrait;

    /**
     * @param iterable<mixed> $iterable
     *
     * @return int
     */
    public static function count(iterable $iterable): int
    {
        $result = 0;
        foreach ($iterable as $iterableValue) {
            ++$result;
        }
        return $result;
    }

    /**
     * @param iterable<mixed> $iterable
     *
     * @return bool
     */
    public static function isEmpty(iterable $iterable): bool
    {
        foreach ($iterable as $iterableValue) {
            return false;
        }
        return true;
    }

    /**
     * @param iterable<mixed, mixed> $iterable
     *
     * @return array<mixed, mixed>
     */
    public static function toArray(iterable $iterable): array
    {
        if (is_array($iterable)) {
            return $iterable;
        }

        $result = [];
        foreach ($iterable as $value) {
            $result[] = $value;
        }

        return $result;
    }
}
