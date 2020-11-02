<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\UnitTests\ConfigTests;

use Elastic\Apm\Impl\Util\ObjectToStringUsingPropertiesTrait;

/**
 * @template T
 */
final class OptionTestValidValue
{
    use ObjectToStringUsingPropertiesTrait;

    /** @var string */
    public $rawValue;

    /**
     * @var mixed
     * @phpstan-var T
     */
    public $parsedValue;

    /**
     * OptionTestValidValue constructor.
     *
     * @param string $rawValue
     * @param mixed  $parsedValue
     *
     * @phpstan-param T $parsedValue
     */
    public function __construct(string $rawValue, $parsedValue)
    {
        $this->rawValue = $rawValue;
        $this->parsedValue = $parsedValue;
    }
}
