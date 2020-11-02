<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Config;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 *
 * @template   T
 */
interface OptionMetadataInterface
{
    /**
     * @return OptionParserInterface
     *
     * @phpstan-return OptionParserInterface<T>
     */
    public function parser(): OptionParserInterface;

    /**
     * @return mixed
     *
     * @phpstan-return T|null
     */
    public function defaultValue();
}
