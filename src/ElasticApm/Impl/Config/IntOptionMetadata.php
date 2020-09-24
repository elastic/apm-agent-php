<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Config;

use Elastic\Apm\Impl\Util\ArrayUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 *
 * @extends OptionMetadataBase<int>
 */
final class IntOptionMetadata extends OptionMetadataBase
{
    public function __construct(int $defaultValue)
    {
        parent::__construct($defaultValue);
    }

    public static function parseValue(string $rawValue): int
    {
        if (is_numeric($rawValue)) {
            $valueAsInt = intval($rawValue);
            if (strval($valueAsInt) === $rawValue) {
                return $valueAsInt;
            }
        }

        throw new ParseException("Not a valid int value. Raw option value: `$rawValue'");
    }

    /**
     * @param string $rawValue
     *
     * @return mixed
     *
     * @phpstan-return int
     */
    public function parse(string $rawValue)
    {
        return self::parseValue($rawValue);
    }
}
