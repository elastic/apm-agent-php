<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\UnitTests\ConfigTests;

use Elastic\Apm\Impl\Config\FloatOptionMetadata;
use Elastic\Apm\Tests\Util\TestRandomUtil;
use PHPUnit\Framework\Assert as PHPUnitAssert;

/**
 * @implements OptionTestMetadataInterface<float>
 */
final class FloatOptionTestMetadata implements OptionTestMetadataInterface
{
    /** @var FloatOptionMetadata */
    private $optMeta;

    public function __construct(FloatOptionMetadata $optMeta)
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

        if (is_null($differentFromParsedValue)) {
            $parsedValue = TestRandomUtil::generateFloatInRange(
                $this->optMeta->minValidValue(),
                $this->optMeta->maxValidValue()
            );
        } else {
            $newRandomValue = TestRandomUtil::generateFloatInRange(
                $this->optMeta->minValidValue(),
                $this->optMeta->maxValidValue(),
                false /* $includeMax */
            );

            $parsedValue = $newRandomValue !== $differentFromParsedValue
                ? $newRandomValue
                : $this->optMeta->maxValidValue();
        }
        PHPUnitAssert::assertNotNull($parsedValue);
        if (!is_null($differentFromParsedValue)) {
            PHPUnitAssert::assertNotEquals($differentFromParsedValue, $parsedValue);
        }

        $rawValue = strval($parsedValue);
    }

    public function invalidRawValues(): iterable
    {
        yield from ['', ' ', '\t', '\r\n', 'a', 'abc', '12.3abc', 'abc1.23', 'a_12.3_b', 'a_12.3E+1', '12.3E+1_b'];

        $valueDiffs = [0.0, 0.001, 0.01, 0.1, 1.1];

        foreach ($valueDiffs as $valueDiff) {
            $invalidValueCandidate = PHP_FLOAT_MAX - $valueDiff;
            if ($this->optMeta->maxValidValue() < $invalidValueCandidate) {
                yield strval($invalidValueCandidate);
            }
            $invalidValueCandidate = -$invalidValueCandidate;
            if ($this->optMeta->minValidValue() > $invalidValueCandidate) {
                yield strval($invalidValueCandidate);
            }
            if ($valueDiff !== 0.0) {
                if ($this->optMeta->minValidValue() !== -PHP_FLOAT_MAX) {
                    yield strval($this->optMeta->minValidValue() - $valueDiff);
                }
                if ($this->optMeta->maxValidValue() !== PHP_FLOAT_MAX) {
                    yield strval($this->optMeta->maxValidValue() + $valueDiff);
                }
            }
        }
    }
}
