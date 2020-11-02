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
final class DurationOptionMetadata extends OptionWithDefaultValueMetadata
{
    public function __construct(
        ?float $minValidValueInMilliseconds,
        ?float $maxValidValueInMilliseconds,
        int $defaultUnits,
        float $defaultValueInMilliseconds
    ) {
        parent::__construct(
            new DurationOptionParser($minValidValueInMilliseconds, $maxValidValueInMilliseconds, $defaultUnits),
            $defaultValueInMilliseconds
        );
    }
}
