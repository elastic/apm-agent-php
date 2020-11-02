<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Config;

use Elastic\Apm\Impl\Util\DbgUtil;
use Elastic\Apm\Impl\Util\ObjectToStringUsingPropertiesTrait;
use Elastic\Apm\Impl\Util\TextUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 *
 * @implements OptionParserInterface<float>
 */
final class DurationOptionParser implements OptionParserInterface
{
    use ObjectToStringUsingPropertiesTrait;

    /** @var float|null */
    private $minValidValueInMilliseconds;

    /** @var float|null */
    private $maxValidValueInMilliseconds;

    /** @var int */
    private $defaultUnits;

    public function __construct(
        ?float $minValidValueInMilliseconds,
        ?float $maxValidValueInMilliseconds,
        int $defaultUnits
    ) {
        $this->minValidValueInMilliseconds = $minValidValueInMilliseconds;
        $this->maxValidValueInMilliseconds = $maxValidValueInMilliseconds;
        $this->defaultUnits = $defaultUnits;
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
        $partWithoutSuffix = '';
        $units = $this->defaultUnits;
        self::splitToValueAndUnits($rawValue, /* ref */ $partWithoutSuffix, /* ref */ $units);

        $auxFloatOptionParser = new FloatOptionParser(null /* minValidValue */, null /* maxValidValue */);
        $parsedValueInMilliseconds
            = self::convertToMilliseconds($auxFloatOptionParser->parse($partWithoutSuffix), $units);

        if (
            (
                (!is_null($this->minValidValueInMilliseconds))
                && ($parsedValueInMilliseconds < $this->minValidValueInMilliseconds)
            )
            || (
                (!is_null($this->maxValidValueInMilliseconds))
                && ($this->maxValidValueInMilliseconds < $parsedValueInMilliseconds)
            )
        ) {
            throw new ParseException(
                'Value is not in range between the valid minimum and maximum values.'
                . ' Raw option value: `' . $rawValue . "'."
                . ' Parsed option value (in milliseconds): ' . $parsedValueInMilliseconds . '.'
                . ' The valid minimum value (in milliseconds): ' . $this->minValidValueInMilliseconds . '.'
                . ' The valid maximum value (in milliseconds): ' . $this->maxValidValueInMilliseconds . '.'
            );
        }

        return $parsedValueInMilliseconds;
    }

    public function defaultUnits(): int
    {
        return $this->defaultUnits;
    }

    public function minValidValueInMilliseconds(): ?float
    {
        return $this->minValidValueInMilliseconds;
    }

    public function maxValidValueInMilliseconds(): ?float
    {
        return $this->maxValidValueInMilliseconds;
    }

    private static function splitToValueAndUnits(string $rawValue, string &$partWithoutSuffix, int &$units): void
    {
        foreach (DurationUnits::$suffixAndIdPairs as $suffixAndIdPair) {
            $suffix = $suffixAndIdPair[0];
            if (TextUtil::isSuffixOf($suffix, $rawValue, /* isCaseSensitive */ false)) {
                $partWithoutSuffix = trim(substr($rawValue, 0, -strlen($suffix)));
                $units = $suffixAndIdPair[1];
                return;
            }
        }
        $partWithoutSuffix = $rawValue;
    }

    public static function convertToMilliseconds(float $srcValue, int $srcValueUnits): float
    {
        switch ($srcValueUnits) {
            case DurationUnits::MILLISECONDS:
                return $srcValue;

            case DurationUnits::SECONDS:
                return $srcValue * 1000;

            case DurationUnits::MINUTES:
                return $srcValue * 60 * 1000;

            default:
                throw new ParseException(
                    'Not a valid time duration units ID.'
                    . ' srcValueUnits: ' . $srcValueUnits . '.'
                    . ' Valid units: ' . DbgUtil::formatValue(DurationUnits::$suffixAndIdPairs) . '.'
                );
        }
    }
}
