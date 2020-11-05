<?php

declare(strict_types=1);

namespace ElasticApmTests\ComponentTests\Util;

use Elastic\Apm\Impl\Config\EnumOptionParser;
use Elastic\Apm\Impl\Config\OptionWithDefaultValueMetadata;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 *
 * @extends OptionWithDefaultValueMetadata<int>
 */
final class AppCodeHostKindOptionMetadata extends OptionWithDefaultValueMetadata
{
    public function __construct()
    {
        parent::__construct(
            new EnumOptionParser(
                'application code host kind' /* <- dbgEnumDesc */,
                [
                    ['CLI_script', AppCodeHostKind::CLI_SCRIPT],
                    ['CLI_builtin_HTTP_server', AppCodeHostKind::CLI_BUILTIN_HTTP_SERVER],
                    ['external_HTTP_server', AppCodeHostKind::EXTERNAL_HTTP_SERVER],
                ],
                false /* <- isCaseSensitive */,
                false /* <- isUnambiguousPrefixAllowed */
            ),
            AppCodeHostKind::NOT_SET /* defaultValue */
        );
    }
}
