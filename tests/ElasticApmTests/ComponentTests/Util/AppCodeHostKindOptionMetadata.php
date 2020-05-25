<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests\Util;

use Elastic\Apm\Impl\Config\EnumOptionMetadata;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class AppCodeHostKindOptionMetadata extends EnumOptionMetadata
{
    public const NAME = 'app_code_host_kind';

    public function __construct()
    {
        parent::__construct(
            'application code host kind',
            AppCodeHostKind::NOT_SET /* defaultValue */,
            [
                'CLI_script'              => AppCodeHostKind::CLI_SCRIPT,
                'CLI_builtin_HTTP_server' => AppCodeHostKind::CLI_BUILTIN_HTTP_SERVER,
                'external_HTTP_server'    => AppCodeHostKind::EXTERNAL_HTTP_SERVER,
            ]
        );
    }
}
