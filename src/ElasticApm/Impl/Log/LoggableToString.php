<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Log;

use Elastic\Apm\Impl\Util\StaticClassTrait;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class LoggableToString
{
    use StaticClassTrait;

    public const DEFAULT_LENGTH_LIMIT = 1000;

    /**
     * @param mixed $value
     * @param bool  $prettyPrint
     * @param int   $lengthLimit
     *
     * @return string
     */
    public static function convert(
        $value,
        bool $prettyPrint = false,
        int $lengthLimit = self::DEFAULT_LENGTH_LIMIT
    ): string {
        return LoggableToEncodedJson::convert($value, $prettyPrint, $lengthLimit);
    }
}
