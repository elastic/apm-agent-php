<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Config;

use Elastic\Apm\Impl\Util\StaticClassTrait;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class OptionNames
{
    use StaticClassTrait;

    public const ENABLED = 'enabled';
    public const SERVER_URL = 'server_url';
    public const SERVICE_NAME = 'service_name';
}
