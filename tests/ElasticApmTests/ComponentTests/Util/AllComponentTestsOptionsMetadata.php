<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests\Util;

use Elastic\Apm\Impl\Config\LogLevelOptionMetadata;
use Elastic\Apm\Impl\Config\NullableIntOptionMetadata;
use Elastic\Apm\Impl\Config\NullableStringOptionMetadata;
use Elastic\Apm\Impl\Config\OptionMetadataInterface;
use Elastic\Apm\Impl\Log\Level;
use Elastic\Apm\Impl\Util\StaticClassTrait;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class AllComponentTestsOptionsMetadata
{
    use StaticClassTrait;

    public const APP_CODE_CLASS_OPTION_NAME = 'app_code_class';
    public const APP_CODE_METHOD_OPTION_NAME = 'app_code_method';
    public const RESOURCES_CLEANER_PORT_OPTION_NAME = 'resources_cleaner_port';
    public const RESOURCES_CLEANER_SERVER_ID_OPTION_NAME = 'resources_cleaner_server_id';
    public const ROOT_PROCESS_ID_OPTION_NAME = 'root_process_id';
    public const THIS_SERVER_ID_OPTION_NAME = 'this_server_id';
    public const THIS_SERVER_PORT_OPTION_NAME = 'this_server_port';

    /**
     * @return array<string, OptionMetadataInterface<mixed>> Option name to metadata
     */
    public static function build(): array
    {
        return [
            self::APP_CODE_CLASS_OPTION_NAME              => new NullableStringOptionMetadata(),
            AppCodeHostKindOptionMetadata::NAME           => new AppCodeHostKindOptionMetadata(),
            self::APP_CODE_METHOD_OPTION_NAME             => new NullableStringOptionMetadata(),
            'app_code_php_exe'                            => new NullableStringOptionMetadata(),
            'app_code_php_ini'                            => new NullableStringOptionMetadata(),
            'log_level'                                   => new LogLevelOptionMetadata(Level::TRACE),
            self::RESOURCES_CLEANER_PORT_OPTION_NAME      => new NullableIntOptionMetadata(),
            self::RESOURCES_CLEANER_SERVER_ID_OPTION_NAME => new NullableStringOptionMetadata(),
            self::ROOT_PROCESS_ID_OPTION_NAME             => new NullableIntOptionMetadata(),
            self::THIS_SERVER_ID_OPTION_NAME              => new NullableStringOptionMetadata(),
            self::THIS_SERVER_PORT_OPTION_NAME            => new NullableIntOptionMetadata(),
        ];
    }
}
