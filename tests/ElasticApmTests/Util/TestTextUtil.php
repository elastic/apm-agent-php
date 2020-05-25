<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\Util;

use Elastic\Apm\Impl\Util\StaticClassTrait;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class TestTextUtil
{
    use StaticClassTrait;

    public static function suffixFrom(string $text, int $startPos): string
    {
        return substr($text, $startPos, strlen($text) - $startPos);
    }
}
