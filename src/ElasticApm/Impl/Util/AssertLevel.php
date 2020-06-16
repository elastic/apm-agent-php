<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Util;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class AssertLevel
{
    use StaticClassTrait;

    /** @var int */
    public const OFF = 0;

    /** @var int */
    public const O_1 = self::OFF + 1;

    /** @var int */
    public const O_N = self::O_1 + 1;

    /** @var int */
    public const O_ALL = PHP_INT_MAX;
}
