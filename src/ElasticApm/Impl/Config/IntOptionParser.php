<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Config;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 *
 * @extends NumericOptionParserBase<int>
 */
final class IntOptionParser extends NumericOptionParserBase
{
    protected function dbgValueTypeDesc(): string
    {
        return 'int';
    }

    public static function isValidFormat(string $rawValue): bool
    {
        return filter_var($rawValue, FILTER_VALIDATE_INT) !== false;
    }

    protected function stringToNumber(string $rawValue)
    {
        return intval($rawValue);
    }
}
