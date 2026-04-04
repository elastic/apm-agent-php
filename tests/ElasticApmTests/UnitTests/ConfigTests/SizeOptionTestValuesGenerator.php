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

use Elastic\Apm\Impl\Config\SizeOptionParser;

/**
 * @implements OptionTestValuesGeneratorInterface<float>
 */
final class SizeOptionTestValuesGenerator implements OptionTestValuesGeneratorInterface
{
    /** @var SizeOptionParser */
    private $optionParser;

    public function __construct(SizeOptionParser $optionParser)
    {
        $this->optionParser = $optionParser;
    }

    private function isInValidRange(float $valueInBytes): bool
    {
        return (($this->optionParser->minValidValueInBytes() === null) || ($valueInBytes >= $this->optionParser->minValidValueInBytes()))
            && (($this->optionParser->maxValidValueInBytes() === null) || ($valueInBytes <= $this->optionParser->maxValidValueInBytes()));
    }

    /**
     * @param int $valueWithoutUnits
     */
    private function convertFromDefaultUnitsToBytes(int $valueWithoutUnits): float
    {
        return SizeOptionParser::convertToBytes($valueWithoutUnits, $this->optionParser->defaultUnits());
    }

    public function validValues(): iterable
    {
        /**
         * @var array<array{string, float}>
         */
        $predefinedValidValues = [
            ['1', $this->convertFromDefaultUnitsToBytes(1)],
            ['1B', 1.0],
            ['2 KB', 2.0 * 1024],
            ['10MB', 10.0 * 1024 * 1024],
            ['3 gb', 3.0 * 1024 * 1024 * 1024],
            ['123 B', 123.0],
        ];

        foreach ($predefinedValidValues as $rawAndParsedValuesPair) {
            if ($this->isInValidRange($rawAndParsedValuesPair[1])) {
                yield new OptionTestValidValue($rawAndParsedValuesPair[0], $rawAndParsedValuesPair[1]);
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
            '1.0',
            '1.5MB',
            '1m',
            '1MBx',
            '3a2MB',
            '0',
            '0B',
            '0 KB',
            '-1',
            '-1B',
            '-1MB',
        ];

        /** @var OptionTestValidValue<float> $validValue */
        foreach ($this->validValues() as $validValue) {
            foreach (['a', 'z'] as $invalidSizeUnitsSuffix) {
                yield $validValue->rawValue . $invalidSizeUnitsSuffix;
            }
        }
    }
}
