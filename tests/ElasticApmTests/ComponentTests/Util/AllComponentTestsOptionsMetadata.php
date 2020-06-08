<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests\Util;

use Elastic\Apm\Impl\Config\IntOptionMetadata;
use Elastic\Apm\Impl\Config\LogLevelOptionMetadata;
use Elastic\Apm\Impl\Config\NullableStringOptionMetadata;
use Elastic\Apm\Impl\Config\OptionMetadataInterface;
use Elastic\Apm\Impl\Config\StringOptionMetadata;
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

    public const SPAWNED_PROCESSES_CLEANER_PORT_OPTION_NAME = 'spawned_processes_cleaner_port';
    public const TEST_ENV_ID_OPTION_NAME = 'test_env_id';
    public const INT_OPTION_NOT_SET = -1;

    /**
     * @return array<string, OptionMetadataInterface<mixed>> Option name to metadata
     */
    public static function build(): array
    {
        return [
            AppCodeHostKindOptionMetadata::NAME              => new AppCodeHostKindOptionMetadata(),
            'app_code_php_cmd'                               => new NullableStringOptionMetadata(),
            'log_level'                                      => new LogLevelOptionMetadata(Level::TRACE),
            'mock_apm_server_port'                           => new IntOptionMetadata(self::INT_OPTION_NOT_SET),
            self::SPAWNED_PROCESSES_CLEANER_PORT_OPTION_NAME => new IntOptionMetadata(self::INT_OPTION_NOT_SET),
            self::TEST_ENV_ID_OPTION_NAME                    => new StringOptionMetadata(''),
        ];
    }
}
