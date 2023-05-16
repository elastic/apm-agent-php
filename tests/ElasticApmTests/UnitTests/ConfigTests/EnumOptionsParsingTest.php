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

use Elastic\Apm\Impl\Config\EnumOptionParser;
use ElasticApmTests\Util\TestCaseBase;

class EnumOptionsParsingTest extends TestCaseBase
{
    public function testEnumWithSomeEntriesArePrefixOfOtherOnes(): void
    {
        $optionParser = new EnumOptionParser(
            '<enum defined in ' . __METHOD__ . '>'/* <- dbgEnumDesc */,
            /* nameValuePairs */
            [
                ['enumEntry', 'enumEntry_value'],
                ['enumEntryWithSuffix', 'enumEntryWithSuffix_value'],
                ['enumEntryWithSuffix2', 'enumEntryWithSuffix2_value'],
                ['anotherEnumEntry', 'anotherEnumEntry_value'],
            ],
            true  /* isCaseSensitive */,
            true  /* isUnambiguousPrefixAllowed */
        );

        /** @noinspection SpellCheckingInspection */
        $testValuesGenerator = new EnumOptionTestValuesGenerator(
            $optionParser,
            /* additionalValidValues: */
            [
                new OptionTestValidValue(" anotherEnumEntry\t\n", 'anotherEnumEntry_value'),
                new OptionTestValidValue("anotherEnumEnt  \n ", 'anotherEnumEntry_value'),
                new OptionTestValidValue("another  \n ", 'anotherEnumEntry_value'),
                new OptionTestValidValue('a', 'anotherEnumEntry_value'),
                new OptionTestValidValue(' enumEntry', 'enumEntry_value'),
                new OptionTestValidValue("\t  enumEntryWithSuffix\n ", 'enumEntryWithSuffix_value'),
                new OptionTestValidValue('enumEntryWithSuffix2', 'enumEntryWithSuffix2_value'),
            ],
            /* additionalInvalidRawValues: */
            [
                'e',
                'enum',
                'enumEnt',
                'enumEntr',
                'enumEntryWithSuffi',
                'enumEntryWithSuffix2_',
                'ENUMENTRY',
                'enumEntryWithSUFFIX',
                'ENUMEntryWithSuffix2',
                'anotherenumentry',
                'Another',
                'A',
            ]
        );

        /** @phpstan-ignore-next-line */
        VariousOptionsParsingTest::parseValidValueTestImpl($testValuesGenerator, $optionParser);
        /** @phpstan-ignore-next-line */
        VariousOptionsParsingTest::parseInvalidValueTestImpl($testValuesGenerator, $optionParser);
    }
}
