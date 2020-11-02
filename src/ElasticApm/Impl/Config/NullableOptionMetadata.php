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
abstract class NullableOptionMetadata implements OptionMetadataInterface
{
    use ObjectToStringUsingPropertiesTrait;

    /**
     * @var OptionParserInterface
     * @phpstan-var OptionParserInterface<T>
     */
    private $parser;

    /**
     * @param OptionParserInterface $parser
     *
     * @phpstan-param OptionParserInterface<T> $parser
     */
    public function __construct(OptionParserInterface $parser)
    {
        $this->parser = $parser;
    }

    public function parser(): OptionParserInterface
    {
        return $this->parser;
    }

    /**
     * @return null
     */
    public function defaultValue()
    {
        return null;
    }
}
