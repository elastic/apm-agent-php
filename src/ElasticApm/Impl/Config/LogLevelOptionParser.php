<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Config;

use Elastic\Apm\Impl\Log\Level;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 *
 * @extends EnumOptionParser<int>
 */
final class LogLevelOptionParser extends EnumOptionParser
{
    public function __construct()
    {
        parent::__construct(
            'log level' /* dbgEnumDesc */,
            Level::nameIntPairs(),
            false /* <- isCaseSensitive */,
            true /* <- isUnambiguousPrefixAllowed */
        );
    }
}
