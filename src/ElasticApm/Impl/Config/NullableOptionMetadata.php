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
abstract class NullableOptionMetadata extends OptionMetadata
{
    /**
     * @var OptionParser
     * @phpstan-var OptionParser<T>
     */
    private $parser;

    /**
     * @param OptionParser $parser
     *
     * @phpstan-param OptionParser<T> $parser
     */
    public function __construct(OptionParser $parser)
    {
        $this->parser = $parser;
    }

    public function parser(): OptionParser
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
