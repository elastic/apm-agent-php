<?php

declare(strict_types=1);

namespace ElasticApmTests\UnitTests\ConfigTests;

/**
 * @template T
 */
interface OptionTestValuesGeneratorInterface
{
    public const NUMBER_OF_RANDOM_VALUES_TO_TEST = 10;

    /**
     * @return iterable<OptionTestValidValue<mixed>>
     * @phpstan-return iterable<OptionTestValidValue<T>>
     */
    public function validValues(): iterable;

    /**
     * @return iterable<string>
     */
    public function invalidRawValues(): iterable;
}
