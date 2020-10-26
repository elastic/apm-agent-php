<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\UnitTests\ConfigTests;

use Elastic\Apm\Impl\Config\BoolOptionMetadata;
use Elastic\Apm\Impl\Util\SingletonInstanceTrait;
use Elastic\Apm\Impl\Util\TextUtil;
use Elastic\Apm\Tests\Util\TestRandomUtil;
use Elastic\Apm\Tests\Util\RangeUtil;
use PHPUnit\Framework\Assert as PHPUnitAssert;

/**
 * @implements OptionTestMetadataInterface<bool>
 */
final class BoolOptionTestMetadata implements OptionTestMetadataInterface
{
    use SingletonInstanceTrait;

    public function randomValidValue(
        int $index,
        string &$rawValue,
        &$parsedValue,
        $differentFromParsedValue = null
    ): void {
        if (!is_null($differentFromParsedValue)) {
            PHPUnitAssert::assertIsBool($differentFromParsedValue);
        }

        $parsedValue = is_null($differentFromParsedValue) ? TestRandomUtil::generateBool() : !$differentFromParsedValue;
        PHPUnitAssert::assertNotNull($parsedValue);
        if (!is_null($differentFromParsedValue)) {
            PHPUnitAssert::assertNotEquals($differentFromParsedValue, $parsedValue);
        }
        $rawValues = $parsedValue ? BoolOptionMetadata::$trueRawValues : BoolOptionMetadata::$falseRawValues;
        $rawValue = $rawValues[mt_rand(0, count($rawValues) - 1)];
        $rawValueLen = strlen($rawValue);
        foreach (RangeUtil::generate(0, $rawValueLen) as $i) {
            if (TestRandomUtil::generateBool()) {
                $rawValue[$i] = chr(TextUtil::toUpperCaseLetter(ord($rawValue[$i])));
            }
        }
    }

    public function invalidRawValues(): iterable
    {
        yield from ['', ' ', '\t', '\r\n', 'a', 'abc', '123'];

        foreach (BoolOptionMetadata::$trueRawValues as $trueRawValue) {
            yield $trueRawValue . '_X';
            yield 'X_' . $trueRawValue;
        }

        foreach (BoolOptionMetadata::$falseRawValues as $falseRawValue) {
            yield $falseRawValue . '_X';
            yield 'X_' . $falseRawValue;
        }
    }
}
