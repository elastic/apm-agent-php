<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Config;

use Elastic\Apm\Impl\Util\StaticClassTrait;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class OptionDefaultValues
{
    use StaticClassTrait;

    public const TRANSACTION_MAX_SPANS = 500;
}
