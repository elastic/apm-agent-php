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
 * @extends    OptionMetadata<T>
 */
abstract class OptionWithDefaultValueMetadata extends OptionMetadata
{
    /**
     * @var OptionParser
     * @phpstan-var OptionParser<T>
     */
    private $parser;

    /**
     * @var mixed
     * @phpstan-var T
     */
    private $defaultValue;

    /**
     * @param OptionParser $parser
     * @param mixed        $defaultValue
     *
     * @phpstan-param OptionParser<T> $parser
     * @phpstan-param T $defaultValue
     */
    public function __construct(OptionParser $parser, $defaultValue)
    {
        $this->parser = $parser;
        $this->defaultValue = $defaultValue;
    }

    public function parser(): OptionParser
    {
        return $this->parser;
    }

    /**
     * @return mixed
     * @phpstan-return T
     */
    public function defaultValue()
    {
        return $this->defaultValue;
    }
}
