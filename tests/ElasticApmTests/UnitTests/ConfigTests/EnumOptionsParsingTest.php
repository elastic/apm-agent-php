<?php

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

        VariousOptionsParsingTest::parseValidValueTestImpl($testValuesGenerator, $optionParser);
        VariousOptionsParsingTest::parseInvalidValueTestImpl($testValuesGenerator, $optionParser);
    }
}
