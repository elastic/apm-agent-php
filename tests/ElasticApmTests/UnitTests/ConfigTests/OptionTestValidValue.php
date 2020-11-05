<?php

declare(strict_types=1);

namespace ElasticApmTests\UnitTests\ConfigTests;

use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableTrait;

/**
 * @template T
 */
final class OptionTestValidValue implements LoggableInterface
{
    use LoggableTrait;

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
