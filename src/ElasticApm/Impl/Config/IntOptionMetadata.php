<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Config;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 *
 * @extends OptionWithDefaultValueMetadata<int>
 */
final class IntOptionMetadata extends OptionWithDefaultValueMetadata
{
    public function __construct(?int $minValidValue, ?int $maxValidValue, int $defaultValue)
    {
        parent::__construct(new IntOptionParser($minValidValue, $maxValidValue), $defaultValue);
    }
}
