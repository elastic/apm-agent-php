<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\Util;

use Elastic\Apm\Impl\Util\StaticClassTrait;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class TextUtilForTests
{
    use StaticClassTrait;

    /**
     * @param string $input
     *
     * @return iterable<int>
     */
    public static function iterateOverChars(string $input): iterable
    {
        foreach (RangeUtilForTests::generateUpTo(strlen($input)) as $i) {
            yield ord($input[$i]);
        }
    }
}
