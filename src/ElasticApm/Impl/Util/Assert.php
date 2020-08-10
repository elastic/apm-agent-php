<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Util;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class Assert
{
    use StaticClassTrait;

    /** @var int */
    private static $maxEnabledLevel = AssertLevel::O_1;

    public static function configure(int $maxEnabledLevel): void
    {
        // TODO: Sergey Kleyman: Configure based on input configuration
        self::$maxEnabledLevel = $maxEnabledLevel;
    }

    public static function ifEnabled(): ?EnabledAssertProxy
    {
        return self::ifEnabledLevel(AssertLevel::O_1);
    }

    public static function ifOnLevelEnabled(): ?EnabledAssertProxy
    {
        return self::ifEnabledLevel(AssertLevel::O_N);
    }

    private static function ifEnabledLevel(int $statementLevel): ?EnabledAssertProxy
    {
        return (self::$maxEnabledLevel >= $statementLevel) ? new EnabledAssertProxy() : null;
    }
}
