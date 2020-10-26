<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\UnitTests\ConfigTests;

use Elastic\Apm\Impl\Util\SingletonInstanceTrait;
use Elastic\Apm\Tests\Util\TestRandomUtil;
use PHPUnit\Framework\Assert as PHPUnitAssert;

/**
 * @implements OptionTestMetadataInterface<int>
 */
final class IntOptionTestMetadata implements OptionTestMetadataInterface
{
    use SingletonInstanceTrait;

    public function randomValidValue(
        int $index,
        string &$rawValue,
        &$parsedValue,
        $differentFromParsedValue = null
    ): void {
        if (!is_null($differentFromParsedValue)) {
            PHPUnitAssert::assertIsInt($differentFromParsedValue);
        }

        if (is_null($differentFromParsedValue)) {
            $parsedValue = TestRandomUtil::generateInt();
        } else {
            $newRandomValue = TestRandomUtil::generateIntInRange(PHP_INT_MIN, PHP_INT_MAX - 1);
            $parsedValue = $newRandomValue < $differentFromParsedValue ? $newRandomValue : ($newRandomValue + 1);
        }
        PHPUnitAssert::assertNotNull($parsedValue);
        if (!is_null($differentFromParsedValue)) {
            PHPUnitAssert::assertNotEquals($differentFromParsedValue, $parsedValue);
        }

        $rawValue = strval($parsedValue);
    }

    public function invalidRawValues(): iterable
    {
        yield from ['', ' ', '\t', '\r\n', 'a', 'abc', '123abc', 'abc123', 'a_123_b'];
    }
}
