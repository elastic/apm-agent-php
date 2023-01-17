<?php

/*
 * Licensed to Elasticsearch B.V. under one or more contributor
 * license agreements. See the NOTICE file distributed with
 * this work for additional information regarding copyright
 * ownership. Elasticsearch B.V. licenses this file to you under
 * the Apache License, Version 2.0 (the "License"); you may
 * not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations
 * under the License.
 */

declare(strict_types=1);

namespace ElasticApmTests\UnitTests\ConfigTests;

use Elastic\Apm\Impl\Config\AllOptionsMetadata;
use Elastic\Apm\Impl\Config\BoolOptionParser;
use Elastic\Apm\Impl\Config\DurationOptionMetadata;
use Elastic\Apm\Impl\Config\DurationOptionParser;
use Elastic\Apm\Impl\Config\DurationUnits;
use Elastic\Apm\Impl\Config\EnumOptionParser;
use Elastic\Apm\Impl\Config\FloatOptionMetadata;
use Elastic\Apm\Impl\Config\FloatOptionParser;
use Elastic\Apm\Impl\Config\IntOptionParser;
use Elastic\Apm\Impl\Config\OptionMetadata;
use Elastic\Apm\Impl\Config\OptionParser;
use Elastic\Apm\Impl\Config\ParseException;
use Elastic\Apm\Impl\Config\Parser;
use Elastic\Apm\Impl\Config\StringOptionParser;
use Elastic\Apm\Impl\Config\WildcardListOptionParser;
use Elastic\Apm\Impl\Log\LoggableToString;
use Elastic\Apm\Impl\Util\DbgUtil;
use Elastic\Apm\Impl\Util\RangeUtil;
use ElasticApmTests\ComponentTests\Util\AllComponentTestsOptionsMetadata;
use ElasticApmTests\ComponentTests\Util\ConfigSnapshotForTests;
use ElasticApmTests\ComponentTests\Util\CustomOptionParser;
use ElasticApmTests\Util\IterableUtilForTests;
use ElasticApmTests\Util\RandomUtilForTests;
use ElasticApmTests\Util\TestCaseBase;
use RuntimeException;
use SebastianBergmann\GlobalState\Snapshot;

class VariousOptionsParsingTest extends TestCaseBase
{
    /**
     * @param OptionMetadata<mixed> $optMeta
     *
     * @return OptionTestValuesGeneratorInterface<mixed>
     */
    private static function selectTestValuesGenerator(
        OptionMetadata $optMeta
    ): OptionTestValuesGeneratorInterface {
        $optionParser = $optMeta->parser();

        if ($optionParser instanceof BoolOptionParser) {
            return new EnumOptionTestValuesGenerator( // @phpstan-ignore-line
                $optionParser,
                /* additionalValidValues: */ [new OptionTestValidValue('', false)]
            );
        }
        if ($optionParser instanceof DurationOptionParser) {
            return new DurationOptionTestValuesGenerator($optionParser); // @phpstan-ignore-line
        }
        if ($optionParser instanceof EnumOptionParser) {
            return new EnumOptionTestValuesGenerator($optionParser); // @phpstan-ignore-line
        }
        if ($optionParser instanceof FloatOptionParser) {
            return new FloatOptionTestValuesGenerator($optionParser); // @phpstan-ignore-line
        }
        if ($optionParser instanceof IntOptionParser) {
            return new IntOptionTestValuesGenerator($optionParser); // @phpstan-ignore-line
        }
        if ($optionParser instanceof StringOptionParser) {
            return StringOptionTestValuesGenerator::singletonInstance(); // @phpstan-ignore-line
        }
        if ($optionParser instanceof WildcardListOptionParser) {
            return WildcardListOptionTestValuesGenerator::singletonInstance(); // @phpstan-ignore-line
        }

        throw new RuntimeException('Unknown option metadata type: ' . DbgUtil::getType($optMeta));
    }

    /**
     * @return array<string, OptionMetadata>
     *
     * @phpstan-return array<string, OptionMetadata<mixed>>
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

        return $result; // @phpstan-ignore-line
    }

    /**
     * @return array<string|null, array<string, OptionMetadata<mixed>>>
     */
    private function snapshotClassToOptionsMeta(): array
    {
        return [
            Snapshot::class               => AllOptionsMetadata::get(),
            ConfigSnapshotForTests::class => AllComponentTestsOptionsMetadata::get(),
            null                          => self::additionalOptionMetas(),
        ];
    }

    /**
     * @return iterable<array{string, OptionMetadata}>
     *
     * @phpstan-return iterable<array{string, OptionMetadata<mixed>}>
     */
    public function allOptionsMetadataProvider(): iterable
    {
        foreach (self::snapshotClassToOptionsMeta() as $optionsMeta) {
            foreach ($optionsMeta as $optMeta) {
                if (!$optMeta->parser() instanceof CustomOptionParser) {
                    yield [LoggableToString::convert($optMeta), $optMeta];
                }
            }
        }
    }

    /**
     * @return iterable<array{string, OptionMetadata}>
     *
     * @phpstan-return iterable<array{string, OptionMetadata<mixed>}>
     */
    public function allOptionsMetadataWithPossibleInvalidRawValuesProvider(): iterable
    {
        foreach ($this->allOptionsMetadataProvider() as $optMetaDescAndDataPair) {
            /** @var OptionMetadata<mixed> $optMeta */
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
     * @param OptionParser<mixed>                       $optParser
     */
    public static function parseInvalidValueTestImpl(
        OptionTestValuesGeneratorInterface $testValuesGenerator,
        OptionParser $optParser
    ): void {
        $invalidRawValues = $testValuesGenerator->invalidRawValues();
        if ($invalidRawValues === []) {
            self::dummyAssert();
            return;
        }

        foreach ($invalidRawValues as $invalidRawValue) {
            $invalidRawValue = self::genOptionalWhitespace() . $invalidRawValue . self::genOptionalWhitespace();
            self::assertThrows(
                ParseException::class,
                function () use ($optParser, $invalidRawValue): void {
                    Parser::parseOptionRawValue($invalidRawValue, $optParser);
                },
                LoggableToString::convert(
                    [
                        'optParser'                => $optParser,
                        'invalidRawValue'          => $invalidRawValue,
                        'strlen($invalidRawValue)' => strlen($invalidRawValue),
                    ]
                    +
                    (strlen($invalidRawValue) === 0 ? [] : ['ord($invalidRawValue[0])' => ord($invalidRawValue[0])])
                )
            );
        }
    }

    /**
     * @dataProvider allOptionsMetadataWithPossibleInvalidRawValuesProvider
     *
     * @param string                $optMetaDbgDesc
     * @param OptionMetadata<mixed> $optMeta
     */
    public function testParseInvalidValue(string $optMetaDbgDesc, OptionMetadata $optMeta): void
    {
        self::parseInvalidValueTestImpl(self::selectTestValuesGenerator($optMeta), $optMeta->parser());
    }

    private static function genOptionalWhitespace(): string
    {
        $whiteSpaceChars = [' ', "\t"];
        $result = '';
        foreach (RangeUtil::generateUpTo(3) as $ignored) {
            $result .= RandomUtilForTests::getRandomValueFromArray($whiteSpaceChars);
        }
        return $result;
    }

    /**
     * @param OptionTestValuesGeneratorInterface<mixed> $testValuesGenerator
     * @param OptionParser<mixed>                       $optParser
     */
    public static function parseValidValueTestImpl(
        OptionTestValuesGeneratorInterface $testValuesGenerator,
        OptionParser $optParser
    ): void {
        $validValues = $testValuesGenerator->validValues();
        if ($validValues === []) {
            self::dummyAssert();
            return;
        }

        /**
         * @param mixed $value
         *
         * @return mixed
         */
        $valueWithDetails = function ($value) {
            if (!is_float($value)) {
                return $value;
            }

            return ['$value' => $value, 'number_format($value)' => number_format($value)];
        };

        /** @var OptionTestValidValue<mixed> $validValueData */
        foreach ($validValues as $validValueData) {
            $validValueData->rawValue =
                self::genOptionalWhitespace() . $validValueData->rawValue . self::genOptionalWhitespace();
            $actualParsedValue = Parser::parseOptionRawValue($validValueData->rawValue, $optParser);
            self::assertSame(
                $validValueData->parsedValue,
                $actualParsedValue,
                LoggableToString::convert(
                    [
                        '$optParser'                   => $optParser,
                        '$validValueData'              => $validValueData,
                        '$validValueData->parsedValue' => $valueWithDetails($validValueData->parsedValue),
                        '$actualParsedValue'           => $valueWithDetails($actualParsedValue),
                    ]
                )
            );
        }
    }

    /**
     * @dataProvider allOptionsMetadataProvider
     *
     * @param string                $optMetaDbgDesc
     * @param OptionMetadata<mixed> $optMeta
     */
    public function testParseValidValue(string $optMetaDbgDesc, OptionMetadata $optMeta): void
    {
        self::parseValidValueTestImpl(self::selectTestValuesGenerator($optMeta), $optMeta->parser());
    }
}
