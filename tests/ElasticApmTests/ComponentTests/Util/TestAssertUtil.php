<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests\Util;

use Elastic\Apm\Impl\Util\StaticClassTrait;
use RuntimeException;

final class TestAssertUtil
{
    use StaticClassTrait;

    public static function assertThat(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new RuntimeException('Assertion failed. ' . $message);
        }
    }
}
