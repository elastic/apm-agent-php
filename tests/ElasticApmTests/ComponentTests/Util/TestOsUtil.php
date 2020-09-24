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

    public static function signalName(int $signalNumber): ?string
    {
        foreach (get_defined_constants(true)['pcntl'] as $constantName => $constantValue) {
            // the _ is to ignore SIG_IGN and SIG_DFL and SIG_ERR and SIG_BLOCK and SIG_UNBLOCK and SIG_SETMARK,
            //and maybe more, who knows
            if ($constantValue === $signalNumber && substr($constantName, 0, 3) === "SIG" && $constantName[3] !== "_") {
                return $constantName;
            }
        }
        return null;
    }
}
