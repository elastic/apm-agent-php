<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Config;

use Elastic\Apm\Impl\Util\NumericUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 *
 * @extends OptionMetadataBase<float>
 */
final class FloatOptionMetadata extends OptionMetadataBase
{
    /** @var float */
    private $minValidValue;

    /** @var float */
    private $maxValidValue;

    public function __construct(float $minValidValue, float $maxValidValue, float $defaultValue)
    {
        parent::__construct($defaultValue);
        $this->minValidValue = $minValidValue;
        $this->maxValidValue = $maxValidValue;
    }

    /**
     * @param string $rawValue
     *
     * @return mixed
     *
     * @phpstan-return float
     */
    public function parse(string $rawValue)
    {
        if (!is_numeric($rawValue)) {
            throw new ParseException("Not a valid float value. Raw option value: `$rawValue'");
        }

        $valueAsFloat = floatval($rawValue);

        if (strval($valueAsFloat) !== $rawValue) {
            throw new ParseException("Not a valid float value. Raw option value: `$rawValue'");
        }

        if (!NumericUtil::isInClosedInterval($this->minValidValue, $valueAsFloat, $this->maxValidValue)) {
            throw new ParseException(
                'Value is not in range.'
                . ' Raw option value: `' . $rawValue . "'."
                . ' Parsed option value: ' . $valueAsFloat . '.'
                . ' Range: [ ' . $this->minValidValue . ', ' . $this->maxValidValue . '].'
            );
        }

        return $valueAsFloat;
    }

    public function minValidValue(): float
    {
        return $this->minValidValue;
    }

    public function maxValidValue(): float
    {
        return $this->maxValidValue;
    }
}
