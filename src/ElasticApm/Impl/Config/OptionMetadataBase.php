<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Config;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 *
 * @template   T
 *
 * @implements OptionMetadataInterface<T>
 */
abstract class OptionMetadataBase implements OptionMetadataInterface
{
    /**
     * @var mixed
     * @phpstan-var T
     */
    private $defaultValue;

    /**
     * @param mixed $defaultValue
     *
     * @phpstan-param T $defaultValue
     */
    public function __construct($defaultValue)
    {
        $this->defaultValue = $defaultValue;
    }

    abstract public function parse(string $rawValue);

    public function defaultValue()
    {
        return $this->defaultValue;
    }
}
