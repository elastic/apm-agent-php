<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\UnitTests\ConfigTests;

use Elastic\Apm\Impl\Config\AllOptionsMetadata;
use Elastic\Apm\Impl\Config\OptionMetadataInterface;
use Elastic\Apm\Impl\Config\ParseException;
use Elastic\Apm\Tests\UnitTests\Util\UnitTestCaseBase;
use Elastic\Apm\Tests\Util\RangeUtil;

class OptionMetadataUnitTest extends UnitTestCaseBase
{
    private const NUMBER_OF_VALID_VALUES_TO_TEST = 100;

    public function setUp(): void
    {
        // No need to setup tracer
    }

    /**
     * @return iterable<array<mixed>> Option name to metadata
     */
    public function allOptionsMetadataProvider(): iterable
    {
        foreach (AllOptionsMetadata::build() as $optName => $optMeta) {
            yield [$optName, $optMeta];
        }
    }

    /**
     * @return iterable<array<mixed>> Option name to metadata
     */
    public function allPossiblyInvalidOptionsMetadataProvider(): iterable
    {
        foreach (AllOptionsMetadata::build() as $optName => $optMeta) {
            if (empty(OptionTestMetadataMapper::map($optMeta)->invalidRawValues())) {
                continue;
            }
            yield [$optName, $optMeta];
        }
    }

    /**
     * @dataProvider allPossiblyInvalidOptionsMetadataProvider
     *
     * @param string                         $optName
     * @param OptionMetadataInterface<mixed> $optMeta
     */
    public function testParseInvalidValue(string $optName, OptionMetadataInterface $optMeta): void
    {
        $optTestMeta = OptionTestMetadataMapper::map($optMeta);
        foreach ($optTestMeta->invalidRawValues() as $invalidRawValue) {
            $this->assertThrows(
                ParseException::class,
                function () use ($optMeta, $invalidRawValue) {
                    $optMeta->parse($invalidRawValue);
                }
            );
        }
    }

    /**
     * @dataProvider allOptionsMetadataProvider
     *
     * @param string                         $optName
     * @param OptionMetadataInterface<mixed> $optMeta
     */
    public function testParseValidValue(string $optName, OptionMetadataInterface $optMeta): void
    {
        $optTestMeta = OptionTestMetadataMapper::map($optMeta);

        $lastParsedValue = null;
        foreach (RangeUtil::generate(0, self::NUMBER_OF_VALID_VALUES_TO_TEST) as $i) {
            $rawValue = '';
            $expectedParsedValue = null;
            $optTestMeta->randomValidValue($i, /* ref */ $rawValue, /* ref */ $expectedParsedValue, $lastParsedValue);
            $this->assertSame($expectedParsedValue, $optMeta->parse($rawValue));
            $lastParsedValue = $expectedParsedValue;
        }
    }
}
