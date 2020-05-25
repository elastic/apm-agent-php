<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\UnitTests\ConfigTests;

/**
 * @template T
 */
interface OptionTestMetadataInterface
{
    /**
     * @param string     $rawValue
     * @param mixed      $parsedValue
     * @param mixed|null $differentFromParsedValue
     *
     * @return void
     *
     * @phpstan-param  T $differentFromParsedValue
     * @phpstan-param  T|null $parsedValue
     */
    public function randomValidValue(string &$rawValue, &$parsedValue, $differentFromParsedValue = null): void;

    /**
     * @return iterable<string>
     */
    public function invalidRawValues(): iterable;
}
