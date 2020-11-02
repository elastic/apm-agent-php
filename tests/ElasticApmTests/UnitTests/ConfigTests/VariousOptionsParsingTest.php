<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\UnitTests\ConfigTests;

use Elastic\Apm\Impl\Config\AllOptionsMetadata;
use Elastic\Apm\Impl\Config\DurationOptionMetadata;
use Elastic\Apm\Impl\Config\DurationOptionParser;
use Elastic\Apm\Impl\Config\DurationUnits;
use Elastic\Apm\Impl\Config\EnumOptionParser;
use Elastic\Apm\Impl\Config\FloatOptionMetadata;
use Elastic\Apm\Impl\Config\FloatOptionParser;
use Elastic\Apm\Impl\Config\IntOptionParser;
use Elastic\Apm\Impl\Config\OptionMetadataInterface;
use Elastic\Apm\Impl\Config\OptionParserInterface;
use Elastic\Apm\Impl\Config\ParseException;
use Elastic\Apm\Impl\Config\Parser;
use Elastic\Apm\Impl\Config\StringOptionParser;
use Elastic\Apm\Impl\Util\DbgUtil;
use Elastic\Apm\Tests\Util\TestCaseBase;
use Elastic\Apm\Tests\Util\IterableUtilForTests;
use Elastic\Apm\Tests\ComponentTests\Util\AllComponentTestsOptionsMetadata;
use Elastic\Apm\Tests\ComponentTests\Util\CustomOptionParser;
use Elastic\Apm\Tests\ComponentTests\Util\TestConfigSnapshot;
use RuntimeException;
use SebastianBergmann\GlobalState\Snapshot;

class VariousOptionsParsingTest extends TestCaseBase
{
    /**
     * @param OptionMetadataInterface<mixed> $optMeta
     *
     * @return OptionTestValuesGeneratorInterface<mixed>
     */
    private static function selectTestValuesGenerator(
        OptionMetadataInterface $optMeta
    ): OptionTestValuesGeneratorInterface {
        $optionParser = $optMeta->parser();
        if ($optionParser instanceof DurationOptionParser) {
            return new DurationOptionTestValuesGenerator($optionParser);
        }
        if ($optionParser instanceof EnumOptionParser) {
            return new EnumOptionTestValuesGenerator($optionParser);
        }
        if ($optionParser instanceof FloatOptionParser) {
            return new FloatOptionTestValuesGenerator($optionParser);
        }
        if ($optionParser instanceof IntOptionParser) {
            return new IntOptionTestValuesGenerator($optionParser);
        }
        if ($optionParser instanceof StringOptionParser) {
            return StringOptionTestValuesGenerator::singletonInstance();
        }

        throw new RuntimeException('Unknown option metadata type: ' . DbgUtil::getType($optMeta));
    }

    /**
     * @return array<string, OptionMetadataInterface>
     *
     * @phpstan-return array<string, OptionMetadataInterface<mixed>>
     */
    private function additionalOptionMetas(): array
    {
        $result = [];

        $result['Duration s units'] = new DurationOptionMetadata(
            10.0 /* minValidValueInMilliseconds */,
            20.0 /* maxValidValueInMilliseconds */,
            DurationUnits::SECONDS /* <- defaultUnits: */,
            15.0 /* <- defaultValueInMilliseconds */
        );

        $result['Duration m units'] = new DurationOptionMetadata(
            null /* minValidValueInMilliseconds */,
            null /* maxValidValueInMilliseconds */,
            DurationUnits::MINUTES /* <- defaultUnits: */,
            123 * 60 * 1000.0 /* <- defaultValueInMilliseconds */
        );

        $result['Float without constrains'] = new FloatOptionMetadata(
            null /* minValidValue */,
            null /* maxValidValue */,
            123.321 /* defaultValue */
        );

        $result['Float only with min constrain'] = new FloatOptionMetadata(
            -1.0 /* minValidValue */,
            null /* maxValidValue */,
            456.789 /* defaultValue */
        );

        $result['Float only with max constrain'] = new FloatOptionMetadata(
            null /* minValidValue */,
            1.0 /* maxValidValue */,
            -987.654 /* defaultValue */
        );

        return $result;
    }

    /**
     * @return array<string|null, array>
     */
    private function snapshotClassToOptionsMeta(): array
    {
        return [
            Snapshot::class           => AllOptionsMetadata::build(),
            TestConfigSnapshot::class => AllComponentTestsOptionsMetadata::build(),
            null                      => self::additionalOptionMetas(),
        ];
    }

    /**
     * @return iterable<array{string, OptionMetadataInterface}>
     *
     * @phpstan-return iterable<array{string, OptionMetadataInterface<mixed>}>
     */
    public function allOptionsMetadataProvider(): iterable
    {
        foreach (self::snapshotClassToOptionsMeta() as $optionsMeta) {
            foreach ($optionsMeta as $optMeta) {
                if (!$optMeta->parser() instanceof CustomOptionParser) {
                    yield [DbgUtil::formatValue($optMeta), $optMeta];
                }
            }
        }
    }

    /**
     * @return iterable<array{string, OptionMetadataInterface}>
     *
     * @phpstan-return iterable<array{string, OptionMetadataInterface<mixed>}>
     */
    public function allOptionsMetadataWithPossibleInvalidRawValuesProvider(): iterable
    {
        foreach ($this->allOptionsMetadataProvider() as $optMetaDescAndDataPair) {
            /** @var OptionMetadataInterface<mixed> $optMeta */
            $optMeta = $optMetaDescAndDataPair[1];
            if (!IterableUtilForTests::isEmpty(self::selectTestValuesGenerator($optMeta)->invalidRawValues())) {
                yield $optMetaDescAndDataPair;
            }
        }
    }

    public function testIntOptionParserIsValidFormat(): void
    {
        self::assertTrue(IntOptionParser::isValidFormat('0'));
        self::assertFalse(IntOptionParser::isValidFormat('0.0'));
        self::assertTrue(IntOptionParser::isValidFormat('+0'));
        self::assertFalse(IntOptionParser::isValidFormat('+0.0'));
        self::assertTrue(IntOptionParser::isValidFormat('-0'));
        self::assertFalse(IntOptionParser::isValidFormat('-0.0'));

        self::assertTrue(IntOptionParser::isValidFormat('1'));
        self::assertFalse(IntOptionParser::isValidFormat('1.0'));
        self::assertTrue(IntOptionParser::isValidFormat('+1'));
        self::assertFalse(IntOptionParser::isValidFormat('+1.0'));
        self::assertTrue(IntOptionParser::isValidFormat('-1'));
        self::assertFalse(IntOptionParser::isValidFormat('-1.0'));
    }

    /**
     * @param OptionTestValuesGeneratorInterface<mixed> $testValuesGenerator
     * @param OptionParserInterface<mixed>              $optParser
     */
    public static function parseInvalidValueTestImpl(
        OptionTestValuesGeneratorInterface $testValuesGenerator,
        OptionParserInterface $optParser
    ): void {
        foreach ($testValuesGenerator->invalidRawValues() as $invalidRawValue) {
            self::assertThrows(
                ParseException::class,
                function () use ($optParser, $invalidRawValue): void {
                    Parser::parseOptionRawValue($invalidRawValue, $optParser);
                },
                '$optParser: ' . DbgUtil::formatValue($optParser) . '.'
                . ' $invalidRawValue: ' . $invalidRawValue . '.'
            );
        }
    }

    /**
     * @dataProvider allOptionsMetadataWithPossibleInvalidRawValuesProvider
     *
     * @param string                         $optMetaDbgDesc
     * @param OptionMetadataInterface<mixed> $optMeta
     *
     * @noinspection PhpUnusedParameterInspection
     */
    public function testParseInvalidValue(string $optMetaDbgDesc, OptionMetadataInterface $optMeta): void
    {
        self::parseInvalidValueTestImpl(self::selectTestValuesGenerator($optMeta), $optMeta->parser());
    }

    /**
     * @param OptionTestValuesGeneratorInterface<mixed> $testValuesGenerator
     * @param OptionParserInterface<mixed>              $optParser
     */
    public static function parseValidValueTestImpl(
        OptionTestValuesGeneratorInterface $testValuesGenerator,
        OptionParserInterface $optParser
    ): void {
        /**
         * @param mixed $expectedParsedValue
         * @param mixed $actualParsedValue
         *
         * @return string
         */
        $displayFloatInFull = function ($expectedParsedValue, $actualParsedValue): string {
            if (!is_float($actualParsedValue)) {
                return '';
            }

            return '$expectedParsedValue: ' . number_format($expectedParsedValue) . '.'
                   . ' $actualParsedValue: ' . number_format($actualParsedValue) . '.';
        };

        /** @var OptionTestValidValue<mixed> $validValueData */
        foreach ($testValuesGenerator->validValues() as $validValueData) {
            $actualParsedValue = Parser::parseOptionRawValue($validValueData->rawValue, $optParser);
            self::assertSame(
                $validValueData->parsedValue,
                $actualParsedValue,
                '$optParser: ' . DbgUtil::formatValue($optParser) . '.'
                . ' $validValueData: `' . DbgUtil::formatValue($validValueData) . '\'.'
                . $displayFloatInFull($validValueData->parsedValue, $actualParsedValue)
            );
        }
    }

    /**
     * @dataProvider   allOptionsMetadataProvider
     *
     * @param string                         $optMetaDbgDesc
     * @param OptionMetadataInterface<mixed> $optMeta
     *
     * @noinspection   PhpUnusedParameterInspection
     */
    public function testParseValidValue(string $optMetaDbgDesc, OptionMetadataInterface $optMeta): void
    {
        self::parseValidValueTestImpl(self::selectTestValuesGenerator($optMeta), $optMeta->parser());
    }
}
