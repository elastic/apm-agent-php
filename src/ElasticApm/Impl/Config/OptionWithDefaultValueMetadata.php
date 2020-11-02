<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Config;

use Elastic\Apm\Impl\Util\ObjectToStringUsingPropertiesTrait;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 *
 * @template   T
 *
 * @implements OptionMetadataInterface<T>
 */
abstract class OptionWithDefaultValueMetadata implements OptionMetadataInterface
{
    use ObjectToStringUsingPropertiesTrait;

    /**
     * @var OptionParserInterface
     * @phpstan-var OptionParserInterface<T>
     */
    private $parser;

    /**
     * @var mixed
     * @phpstan-var T
     */
    private $defaultValue;

    /**
     * @param OptionParserInterface $parser
     * @param mixed                 $defaultValue
     *
     * @phpstan-param OptionParserInterface<T> $parser
     * @phpstan-param T $defaultValue
     */
    public function __construct(OptionParserInterface $parser, $defaultValue)
    {
        $this->parser = $parser;
        $this->defaultValue = $defaultValue;
    }

    public function parser(): OptionParserInterface
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
