<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Config;

use Elastic\Apm\Impl\Util\DbgUtil;
use Elastic\Apm\Impl\Util\TextUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 *
 * @extends OptionMetadataBase<float>
 */
final class TimeDurationOptionMetadata extends OptionMetadataBase
{
    /** @var int */
    private $defaultUnits;

    public function __construct(int $defaultUnits, float $defaultValueInMilliseconds)
    {
        parent::__construct($defaultValueInMilliseconds);
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

        return self::convertToMilliseconds(FloatOptionMetadata::parseValueAsUnconstrained($partWithoutSuffix), $units);
    }

    public function defaultUnits(): int
    {
        return $this->defaultUnits;
    }

    private static function splitToValueAndUnits(string $rawValue, string &$partWithoutSuffix, int &$units): void
    {
        foreach (TimeDurationUnits::$suffixAndIdPairs as $suffixAndIdPair) {
            /** @var string */
            $suffix = $suffixAndIdPair[0];
            if (TextUtil::isSuffixOf($suffix, $rawValue, /* isCaseSensitive */ false)) {
                $partWithoutSuffix = substr($rawValue, 0, -strlen($suffix));
                $units = $suffixAndIdPair[1];
                return;
            }
        }
        $partWithoutSuffix = $rawValue;
    }

    public static function convertToMilliseconds(float $srcValue, int $srcValueUnits): float
    {
        switch ($srcValueUnits) {
            case TimeDurationUnits::MILLISECONDS:
                return $srcValue;

            case TimeDurationUnits::SECONDS:
                return $srcValue * 1000;

            case TimeDurationUnits::MINUTES:
                return $srcValue * 60 * 1000;

            default:
                throw new ParseException(
                    'Not a valid time duration units ID.'
                    . ' srcValueUnits: ' . $srcValueUnits . '.'
                    . ' Valid units: ' . DbgUtil::formatValue(TimeDurationUnits::$suffixAndIdPairs) . '.'
                );
        }
    }
}
