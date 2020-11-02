<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Config;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 *
 * @extends NumericOptionParserBase<float>
 */
final class FloatOptionParser extends NumericOptionParserBase
{
    protected function dbgValueTypeDesc(): string
    {
        return 'float';
    }

    public static function isValidFormat(string $rawValue): bool
    {
        return filter_var($rawValue, FILTER_VALIDATE_FLOAT) !== false;
    }

    protected function stringToNumber(string $rawValue)
    {
        return floatval($rawValue);
    }
}
