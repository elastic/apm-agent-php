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

use Elastic\Apm\Impl\Util\RangeUtil;
use Elastic\Apm\Impl\Util\SingletonInstanceTrait;
use Elastic\Apm\Impl\Util\TextUtil;
use ElasticApmTests\Util\IterableUtilForTests;
use ElasticApmTests\Util\RandomUtilForTests;
use ElasticApmTests\Util\TextUtilForTests;

/**
 * @implements OptionTestValuesGeneratorInterface<string>
 */
final class StringOptionTestValuesGenerator implements OptionTestValuesGeneratorInterface
{
    use SingletonInstanceTrait;

    /**
     * @return iterable<int>
     */
    private static function charsToUse(): iterable
    {
        // latin letters
        foreach (RangeUtil::generateFromToIncluding(ord('A'), ord('Z')) as $charAsInt) {
            yield $charAsInt;
            yield TextUtil::flipLetterCase($charAsInt);
        }

        // digits
        foreach (RangeUtil::generateFromToIncluding(ord('0'), ord('9')) as $charAsInt) {
            yield $charAsInt;
        }

        // punctuation
        yield from TextUtilForTests::iterateOverChars(',:;.!?');

        yield from TextUtilForTests::iterateOverChars('@#$%&*()<>{}[]+-=_~^');
        yield ord('/');
        yield ord('|');
        yield ord('\\');
        yield ord('`');
        yield ord('\'');
        yield ord('"');

        // whitespace
        yield from TextUtilForTests::iterateOverChars(" \t\r\n");
    }

    /**
     * @return iterable<string>
     */
    private function validStrings(): iterable
    {
        yield '';
        yield 'A';
        yield 'abc';
        yield 'abC 123 Xyz';

        /** @var array<int> $charsToUse */
        $charsToUse = IterableUtilForTests::toList(self::charsToUse());

        $stringFromAllCharsToUse = '';
        foreach ($charsToUse as $charToUse) {
            $stringFromAllCharsToUse .= chr($charToUse);
        }
        yield $stringFromAllCharsToUse;

        // any two chars (even the same one twice)
        foreach (RangeUtil::generateUpTo(count($charsToUse)) as $i) {
            foreach (RangeUtil::generateUpTo(count($charsToUse)) as $j) {
                yield chr($charsToUse[$i]) . chr($charsToUse[$j]);
            }
        }

        /** @noinspection PhpUnusedLocalVariableInspection */
        foreach (RangeUtil::generateUpTo(self::NUMBER_OF_RANDOM_VALUES_TO_TEST) as $_) {
            $numberOfChars = RandomUtilForTests::generateIntInRange(1, count($charsToUse));
            $randString = '';
            /** @noinspection PhpUnusedLocalVariableInspection */
            foreach (RangeUtil::generateUpTo($numberOfChars) as $__) {
                $randString .= chr(RandomUtilForTests::generateIntInRange(0, count($charsToUse) - 1));
            }
            yield $randString;
        }
    }

    /**
     * @return iterable<OptionTestValidValue<string>>
     */
    public function validValues(): iterable
    {
        foreach ($this->validStrings() as $validString) {
            yield new OptionTestValidValue($validString, trim($validString));
        }
    }

    public function invalidRawValues(): iterable
    {
        return [];
    }
}
