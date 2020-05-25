<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests\Util;

use Elastic\Apm\Impl\Util\ArrayUtil;
use Elastic\Apm\Impl\Util\StaticClassTrait;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class AppCodeHostKind
{
    use StaticClassTrait;

    public const NOT_SET = 0;
    public const CLI_SCRIPT = self::NOT_SET + 1;
    public const CLI_BUILTIN_HTTP_SERVER = self::CLI_SCRIPT + 1;
    public const EXTERNAL_HTTP_SERVER = self::CLI_BUILTIN_HTTP_SERVER + 1;

    public static function toString(int $intValue): string
    {
        /** @var array<int, string> */
        $intToStringMap = [
            AppCodeHostKind::NOT_SET => 'NOT_SET',
            AppCodeHostKind::CLI_SCRIPT => 'CLI_script',
            AppCodeHostKind::CLI_BUILTIN_HTTP_SERVER => 'CLI_builtin_HTTP_server',
            AppCodeHostKind::EXTERNAL_HTTP_SERVER    => 'external_HTTP_server'
        ];

        return ArrayUtil::getValueIfKeyExistsElse($intValue, $intToStringMap, null) ?? "UNKNOWN ($intValue)";
    }
}
