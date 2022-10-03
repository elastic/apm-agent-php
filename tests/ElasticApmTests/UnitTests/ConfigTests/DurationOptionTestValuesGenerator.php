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

use Elastic\Apm\Impl\Config\DurationOptionParser;
use Elastic\Apm\Impl\Config\DurationUnits;
use Elastic\Apm\Impl\Config\FloatOptionParser;
use Elastic\Apm\Impl\Util\ExceptionUtil;
use RuntimeException;

/**
 * @implements OptionTestValuesGeneratorInterface<float>
 */
final class DurationOptionTestValuesGenerator implements OptionTestValuesGeneratorInterface
{
    /** @var DurationOptionParser */
    private $optionParser;

    /** @var FloatOptionTestValuesGenerator */
    private $auxFloatValuesGenerator;

    public function __construct(DurationOptionParser $optionParser)
    {
        $this->optionParser = $optionParser;
        $this->auxFloatValuesGenerator = self::buildAuxFloatValuesGenerator($optionParser);
    }

    /**
     * @param string $valueAsString
     * @param int    $unitsId
     * @param string $unitsSuffix
     *
     * @return iterable<OptionTestValidValue<float>>
     */
    private function createIfValidValue(
        string $valueAsString,
        int $unitsId,
        string $unitsSuffix
    ): iterable {
        $valueInMilliseconds = DurationOptionParser::convertToMilliseconds(floatval($valueAsString), $unitsId);
        if ($this->auxFloatValuesGenerator->isInValidRange($valueInMilliseconds)) {
            yield new OptionTestValidValue($valueAsString . $unitsSuffix, $valueInMilliseconds);
        }
    }

    public function validValues(): iterable
    {
        /**
         * @param float|int $valueWithoutUnits
         *
         * @return float
         */
        $noUnits = function ($valueWithoutUnits): float {
            return DurationOptionParser::convertToMilliseconds(
                floatval($valueWithoutUnits),
                $this->optionParser->defaultUnits()
            );
        };

        /**
         * We are forced to use list-array of pairs instead of regular associative array
         * because in an associative array if the key is numeric string it's automatically converted to int
         * (see https://www.php.net/manual/en/language.types.array.php)
         *
         * @var array<array<string|float|int>>
         * @phpstan-var array<array{string, float|int}>
         */
        $predefinedValidValues = [
            ['0', 0],
            [' 0 ms', 0],
            ["\t 0 s ", 0],
            ['0m', 0],
            ['1', $noUnits(1)],
            ['0.01', $noUnits(0.01)],
            ['97.5', $noUnits(97.5)],
            ['1ms', 1],
            [" \n 97 \t ms ", 97],
            ['1s', 1000],
            ['1m', 60 * 1000],
            ['0.0', 0],
            ['0.0ms', 0],
            ['0.0s', 0],
            ['0.0m', 0],
            ['1.5ms', 1.5],
            ['1.5s', 1.5 * 1000],
            ['1.5m', 1.5 * 60 * 1000],
            ['-12ms', -12],
            ['-12.5ms', -12.5],
            ['-45s', -45 * 1000],
            ['-45.1s', -45.1 * 1000],
            ['-78m', -78 * 60 * 1000],
            ['-78.2m', -78.2 * 60 * 1000],
        ];
        foreach ($predefinedValidValues as $rawAndParsedValuesPair) {
            if ($this->auxFloatValuesGenerator->isInValidRange($rawAndParsedValuesPair[1])) {
                yield new OptionTestValidValue($rawAndParsedValuesPair[0], floatval($rawAndParsedValuesPair[1]));
            }
        }

        /** @var OptionTestValidValue<float> $validNoUnitsValue */
        foreach ($this->auxFloatValuesGenerator->validValues() as $validNoUnitsValue) {
            foreach (DurationUnits::$suffixAndIdPairs as $durationUnitsSuffixAndIdPair) {
                $unitsSuffixOriginal = $durationUnitsSuffixAndIdPair[0];
                $unitsId = $durationUnitsSuffixAndIdPair[1];
                $unitsSuffixes = [$unitsSuffixOriginal, ' ' . $unitsSuffixOriginal];
                if ($unitsId === $this->optionParser->defaultUnits()) {
                    $unitsSuffixes[] = '';
                }
                foreach ($unitsSuffixes as $unitsSuffix) {
                    $valueInUnits = self::convertFromMilliseconds($validNoUnitsValue->parsedValue, $unitsId);

                    // For float keep only 3 digits after the floating point
                    // for tolerance to error in reverse conversion
                    $roundedValueInUnits = round($valueInUnits, 3);

                    yield from $this->createIfValidValue(strval($roundedValueInUnits), $unitsId, $unitsSuffix);

                    foreach ([ceil($roundedValueInUnits), floor($roundedValueInUnits)] as $intValueInUnits) {
                        if (FloatOptionTestValuesGenerator::isInIntRange($intValueInUnits)) {
                            $intValueInUnitsAsString = strval(intval($intValueInUnits));
                            yield from $this->createIfValidValue($intValueInUnitsAsString, $unitsId, $unitsSuffix);

                            yield from $this->createIfValidValue(strval($intValueInUnits), $unitsId, $unitsSuffix);
                        }
                    }
                }
            }
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
            '3a2s',
            'a32s',
            '3a2ms',
            'a32ms',
        ];

        foreach ($this->auxFloatValuesGenerator->invalidRawValues() as $invalidRawValue) {
            if (!FloatOptionParser::isValidFormat($invalidRawValue)) {
                yield $invalidRawValue;
                continue;
            }

            $invalidValueInMilliseconds = floatval($invalidRawValue);
            if (!$this->auxFloatValuesGenerator->isInValidRange($invalidValueInMilliseconds)) {
                foreach (DurationUnits::$suffixAndIdPairs as $durationUnitsSuffixAndIdPair) {
                    $unitsId = $durationUnitsSuffixAndIdPair[1];
                    $valueInUnits = self::convertFromMilliseconds($invalidValueInMilliseconds, $unitsId);
                    yield $valueInUnits . $durationUnitsSuffixAndIdPair[0];
                    if ($this->optionParser->defaultUnits() === $unitsId) {
                        yield strval($valueInUnits);
                    }
                }
            }
        }

        /** @var OptionTestValidValue<float> $validValue */
        foreach ($this->validValues() as $validValue) {
            foreach (['a', 'z'] as $invalidDurationUnitsSuffix) {
                yield $validValue->rawValue . $invalidDurationUnitsSuffix;
            }
        }
    }

    public static function convertFromMilliseconds(float $valueInMilliseconds, int $dstUnits): float
    {
        switch ($dstUnits) {
            case DurationUnits::MILLISECONDS:
                return $valueInMilliseconds;

            case DurationUnits::SECONDS:
                return $valueInMilliseconds / 1000;

            case DurationUnits::MINUTES:
                return $valueInMilliseconds / (60 * 1000);

            default:
                throw new RuntimeException(
                    ExceptionUtil::buildMessage(
                        'Not a valid time duration units ID',
                        ['dstUnits' => $dstUnits, 'valid time duration units' => DurationUnits::$suffixAndIdPairs]
                    )
                );
        }
    }

    private static function buildAuxFloatValuesGenerator(
        DurationOptionParser $optionParser
    ): FloatOptionTestValuesGenerator {
        $floatOptionParser = new FloatOptionParser(
            $optionParser->minValidValueInMilliseconds(),
            $optionParser->maxValidValueInMilliseconds()
        );

        return new class ($floatOptionParser) extends FloatOptionTestValuesGenerator {
            /**
             * @return iterable<float>
             */
            protected function autoGeneratedInterestingValuesToDiff(): iterable
            {
                foreach (parent::autoGeneratedInterestingValuesToDiff() as $interestingValuesToDiff) {
                    foreach (DurationUnits::$suffixAndIdPairs as $durationUnitsSuffixAndIdPair) {
                        yield DurationOptionTestValuesGenerator::convertFromMilliseconds(
                            $interestingValuesToDiff,
                            $durationUnitsSuffixAndIdPair[1]
                        );
                    }
                }
            }
        };
    }
}
