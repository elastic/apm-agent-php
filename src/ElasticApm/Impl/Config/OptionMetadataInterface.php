<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Config;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 *
 * @template T
 */
interface OptionMetadataInterface
{
    /**
     * @param string $rawValue
     *
     * @return mixed
     * @throws ParseException
     *
     * @phpstan-return T
     */
    public function parse(string $rawValue);

    /**
     * @return mixed
     *
     * @phpstan-return T
     */
    public function defaultValue();
}
