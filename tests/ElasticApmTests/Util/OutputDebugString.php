<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\Util;

use Elastic\Apm\Impl\Util\StaticClassTrait;
use Elastic\Apm\Tests\ComponentTests\Util\TestOsUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class OutputDebugString
{
    use StaticClassTrait;

    /** @var bool */
    private static $isEnabled;

    /**
     * @noinspection PhpUndefinedClassInspection
     * @phpstan-ignore-next-line
     */
    /** @var \FFI */
    private static $ffi;

    public static function isEnabled(): bool
    {
        if (!isset(self::$isEnabled)) {
            self::$isEnabled = self::calcIsEnabled();
        }

        return self::$isEnabled;
    }

    private static function calcIsEnabled(): bool
    {
        // FFI was introduced in PHP 7.4
        if (!TestOsUtil::isWindows() || (PHP_VERSION_ID < 70400)) {
            return false;
        }

        if (!isset(self::$ffi)) {
            try {
                /**
                 * @noinspection PhpUndefinedClassInspection
                 * @phpstan-ignore-next-line
                 */
                self::$ffi = \FFI::cdef('void OutputDebugStringA( const char* test );', 'Kernel32.dll');
            } catch (\Throwable $throwable) {
                return false;
            }
        }

        return true;
    }

    public static function write(string $text): void
    {
        /**
         * @noinspection PhpUndefinedMethodInspection
         * @phpstan-ignore-next-line
         */
        self::$ffi->OutputDebugStringA($text);
    }
}
