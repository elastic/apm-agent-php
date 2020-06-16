<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Config;

use Elastic\Apm\Impl\Util\ArrayUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 *
 * @extends OptionMetadataBase<bool>
 */
final class BoolOptionMetadata extends OptionMetadataBase
{
    /** @var array<string> */
    public static $trueRawValues = ['true', 'yes', 'on', '1'];

    /** @var array<string> */
    public static $falseRawValues = ['false', 'no', 'off', '0'];

    /** @var array<string, bool> */
    private $stringNameToValue;

    /** @inheritDoc */
    public function __construct(bool $defaultValue)
    {
        parent::__construct($defaultValue);
        foreach (self::$trueRawValues as $index => $trueRawValue) {
            $this->stringNameToValue[$trueRawValue] = true;
        }

        foreach (self::$falseRawValues as $index => $falseRawValue) {
            $this->stringNameToValue[$falseRawValue] = false;
        }
    }

    /**
     * @return mixed
     *
     * @phpstan-return bool
     */
    public function parse(string $rawValue)
    {
        $value = ArrayUtil::getValueIfKeyExistsElse(strtolower($rawValue), $this->stringNameToValue, null);
        if (is_null($value)) {
            throw new ParseException("Given value is not a valid boolean option value. Raw option value: `$rawValue'");
        }

        return $value;
    }
}
