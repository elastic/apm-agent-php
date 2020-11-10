<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Config;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 *
 * @extends NullableOptionMetadata<int>
 */
final class NullableLogLevelOptionMetadata extends NullableOptionMetadata
{
    public function __construct()
    {
        parent::__construct(new LogLevelOptionParser());
    }
}
