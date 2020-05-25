<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests\Util;

use Elastic\Apm\Impl\Util\StaticClassTrait;

final class TestOsUtil
{
    use StaticClassTrait;

    public static function isWindows(): bool
    {
        return strnatcasecmp(PHP_OS_FAMILY, 'Windows') === 0;
    }
}
