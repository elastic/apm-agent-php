<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Config;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 *
 * @extends OptionWithDefaultValueMetadata<float>
 */
final class FloatOptionMetadata extends OptionWithDefaultValueMetadata
{
    public function __construct(?float $minValidValue, ?float $maxValidValue, float $defaultValue)
    {
        parent::__construct(new FloatOptionParser($minValidValue, $maxValidValue), $defaultValue);
    }
}
