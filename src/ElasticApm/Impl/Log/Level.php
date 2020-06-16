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

    /** @var int */
    public const OFF = 0;

    /** @var int */
    public const CRITICAL = self::OFF + 1;

    /** @var int */
    public const ERROR = self::CRITICAL + 1;

    /** @var int */
    public const WARNING = self::ERROR + 1;

    /** @var int */
    public const NOTICE = self::WARNING + 1;

    /** @var int */
    public const INFO = self::NOTICE + 1;

    /** @var int */
    public const DEBUG = self::INFO + 1;

    /** @var int */
    public const TRACE = self::DEBUG + 1;
}
