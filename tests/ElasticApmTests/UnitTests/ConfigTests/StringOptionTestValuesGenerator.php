<?php

declare(strict_types=1);

namespace ElasticApmTests\UnitTests\ConfigTests;

use Elastic\Apm\Impl\Util\SingletonInstanceTrait;
use Elastic\Apm\Impl\Util\TextUtil;
use ElasticApmTests\Util\RangeUtilForTests;
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
        foreach (RangeUtilForTests::generateFromToIncluding(ord('A'), ord('Z')) as $charAsInt) {
            yield $charAsInt;
            yield TextUtil::flipLetterCase($charAsInt);
        }

        // digits
        foreach (RangeUtilForTests::generateFromToIncluding(ord('0'), ord('9')) as $charAsInt) {
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

        $charsToUse = IterableUtilForTests::toArray(self::charsToUse());

        $stringFromAllCharsToUse = '';
        foreach ($charsToUse as $charToUse) {
            $stringFromAllCharsToUse .= chr($charToUse);
        }
        yield $stringFromAllCharsToUse;

        // any two chars (even the same one twice)
        foreach (RangeUtilForTests::generateUpTo(count($charsToUse)) as $i) {
            foreach (RangeUtilForTests::generateUpTo(count($charsToUse)) as $j) {
                yield chr($charsToUse[$i]) . chr($charsToUse[$j]);
            }
        }

        /** @noinspection PhpUnusedLocalVariableInspection */
        foreach (RangeUtilForTests::generateUpTo(self::NUMBER_OF_RANDOM_VALUES_TO_TEST) as $_) {
            $numberOfChars = RandomUtilForTests::generateIntInRange(1, count($charsToUse));
            $randString = '';
            /** @noinspection PhpUnusedLocalVariableInspection */
            foreach (RangeUtilForTests::generateUpTo($numberOfChars) as $__) {
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
