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

    public static function ifEnabled(): ?EnabledAssertProxy
    {
        return self::ifEnabledLevel(AssertLevel::O_1);
    }

    private static function ifEnabledLevel(int $statementLevel): ?EnabledAssertProxy
    {
        return (self::$maxEnabledLevel >= $statementLevel) ? new EnabledAssertProxy($statementLevel) : null;
    }
}
