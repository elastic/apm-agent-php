<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Log;

use Elastic\Apm\Impl\Util\StaticClassTrait;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class Level
{
    use StaticClassTrait;

    public const OFF = 0;
    public const CRITICAL = self::OFF + 1;
    public const ERROR = self::CRITICAL + 1;
    public const WARNING = self::ERROR + 1;
    public const NOTICE = self::WARNING + 1;
    public const INFO = self::NOTICE + 1;
    public const DEBUG = self::INFO + 1;
    public const TRACE = self::DEBUG + 1;

    /**
     * @var array<array<string|int>>
     * @phpstan-var array<array{string, int}>
     */
    private static $nameIntPairs
        = [
            ['OFF', Level::OFF],
            ['CRITICAL', Level::CRITICAL],
            ['ERROR', Level::ERROR],
            ['WARNING', Level::WARNING],
            ['NOTICE', Level::NOTICE],
            ['INFO', Level::INFO],
            ['DEBUG', Level::DEBUG],
            ['TRACE', Level::TRACE],
        ];

    /** @var array<int, string> */
    private static $intToName;

    /**
     * @return array<array<string|int>>
     * @phpstan-return array<array{string, int}>
     */
    public static function nameIntPairs(): array
    {
        return self::$nameIntPairs;
    }

    public static function intToName(int $intValueToMap): string
    {
        if (!isset(self::$intToName)) {
            self::$intToName = [];
            foreach (self::$nameIntPairs as $nameIntPair) {
                self::$intToName[$nameIntPair[1]] = $nameIntPair[0];
            }
        }
        return self::$intToName[$intValueToMap];
    }
}
