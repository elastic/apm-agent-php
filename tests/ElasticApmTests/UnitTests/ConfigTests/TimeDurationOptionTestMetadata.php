<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\UnitTests\ConfigTests;

use Elastic\Apm\Impl\Config\TimeDurationOptionMetadata;
use Elastic\Apm\Impl\Config\TimeDurationUnits;
use Elastic\Apm\Impl\Util\DbgUtil;
use Elastic\Apm\Tests\Util\TestRandomUtil;
use PHPUnit\Framework\Assert as PHPUnitAssert;
use RuntimeException;

/**
 * @implements OptionTestMetadataInterface<float>
 */
final class TimeDurationOptionTestMetadata implements OptionTestMetadataInterface
{
    /** @var TimeDurationOptionMetadata */
    private $optMeta;

    public function __construct(TimeDurationOptionMetadata $optMeta)
    {
        $this->optMeta = $optMeta;
    }

    public function randomValidValue(
        int $index,
        string &$rawValue,
        &$parsedValue,
        $differentFromParsedValue = null
    ): void {
        if (!is_null($differentFromParsedValue)) {
            PHPUnitAssert::assertIsFloat($differentFromParsedValue);
        }

        $predefinedValidValues = [
            '1ms' => 1.0,
            '1s' => 1.0 * 1000,
            '1m' => 1.0 * 60 * 1000,
            '1.5ms' => 1.5,
            '1.5s' => 1.5 * 1000,
            '1.5m' => 1.5 * 60 * 1000,
            '-30.1ms' => -30.1,
            '-30.1s' => -30.1 * 1000,
            '-30.1m' => -30.1 * 60 * 1000,
        ];

        if ($index < count($predefinedValidValues)) {
            $rawValue = array_keys($predefinedValidValues)[$index];
            $parsedValue = $predefinedValidValues[$rawValue];
            return;
        }

        $unitsIndex = $index % count(TimeDurationUnits::$suffixAndIdPairs);
        $oneDayInMilliseconds = 24.0 * 60 * 60 * 1000;
        $valueInMilliseconds = TestRandomUtil::generateFloatInRange(-$oneDayInMilliseconds, $oneDayInMilliseconds);
        $unitsSuffixAndId = TimeDurationUnits::$suffixAndIdPairs[$unitsIndex];
        /** @var string */
        $unitsSuffix = $unitsSuffixAndId[0];
        /** @var int */
        $unitsId = $unitsSuffixAndId[1];
        $valueInUnits = self::convertFromMilliseconds($valueInMilliseconds, $unitsId);
        // Use integer for even steps and float for odd steps
        if (($index % 2) === 0) {
            $valueInUnits = intval($valueInUnits);
        } else {
            // For float keep only 3 digits after the floating point
            $valueInUnits = intval($valueInUnits * 1000) / 1000;
        }

        // If selected units are the same as the default ones then omit units on odd cycles
        $omitDefaultUnits = $this->optMeta->defaultUnits() === $unitsId
            ? (intdiv($index, count(TimeDurationUnits::$suffixAndIdPairs)) % 2 !== 0)
            : false;
        $rawValue = strval($valueInUnits) . ($omitDefaultUnits ? '' : $unitsSuffix);
        $parsedValue = TimeDurationOptionMetadata::convertToMilliseconds($valueInUnits, $unitsId);
    }

    private static function convertFromMilliseconds(float $valueInMilliseconds, int $dstUnits): float
    {
        switch ($dstUnits) {
            case TimeDurationUnits::MILLISECONDS:
                return $valueInMilliseconds;

            case TimeDurationUnits::SECONDS:
                return $valueInMilliseconds / 1000;

            case TimeDurationUnits::MINUTES:
                return $valueInMilliseconds / (60 * 1000);

            default:
                throw new RuntimeException(
                    'Not a valid time duration units ID.'
                    . ' dstUnits: ' . $dstUnits . '.'
                    . ' Valid units: ' . DbgUtil::formatValue(TimeDurationUnits::$suffixAndIdPairs) . '.'
                );
        }
    }

    public function invalidRawValues(): iterable
    {
        yield from [
            '',
            ' ',
            '\t',
            '\r\n',
            'a',
            'abc',
            '123abc',
            'abc123',
            'a_123_b',
            '1a',
            '1sm',
            '1m2',
            '1s2',
            '1ms2',
            '3a2m',
            'a32m',
        ];
    }
}
