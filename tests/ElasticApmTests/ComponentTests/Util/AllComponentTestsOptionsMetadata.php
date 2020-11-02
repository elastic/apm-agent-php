<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests\Util;

use Elastic\Apm\Impl\Config\LogLevelOptionMetadata;
use Elastic\Apm\Impl\Config\NullableStringOptionMetadata;
use Elastic\Apm\Impl\Config\OptionMetadataInterface;
use Elastic\Apm\Impl\Log\Level;
use Elastic\Apm\Impl\Util\StaticClassTrait;
use Elastic\Apm\Tests\Util\Deserialization\SerializationTestUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class AllComponentTestsOptionsMetadata
{
    use StaticClassTrait;

    public const APP_CODE_HOST_KIND_OPTION_NAME = 'app_code_host_kind';
    public const APP_CODE_PHP_INI_OPTION_NAME = 'app_code_php_ini';
    public const SHARED_DATA_PER_PROCESS_OPTION_NAME = 'shared_data_per_process';
    public const SHARED_DATA_PER_REQUEST_OPTION_NAME = 'shared_data_per_request';

    /**
     * @return array<string, OptionMetadataInterface> Option name to metadata
     *
     * @phpstan-return array<string, OptionMetadataInterface<mixed>> Option name to metadata
     */
    public static function build(): array
    {
        return [
            self::APP_CODE_HOST_KIND_OPTION_NAME      => new AppCodeHostKindOptionMetadata(),
            'app_code_php_exe'                        => new NullableStringOptionMetadata(),
            self::APP_CODE_PHP_INI_OPTION_NAME        => new NullableStringOptionMetadata(),
            'log_level'                               => new LogLevelOptionMetadata(Level::TRACE),
            self::SHARED_DATA_PER_PROCESS_OPTION_NAME => new NullableCustomOptionMetadata(
                function (string $rawValue): SharedDataPerProcess {
                    return SharedDataPerProcess::deserializeFromJson(
                        SerializationTestUtil::deserializeJson($rawValue, /* asAssocArray */ true)
                    );
                }
            ),
            self::SHARED_DATA_PER_REQUEST_OPTION_NAME => new NullableCustomOptionMetadata(
                function (string $rawValue): SharedDataPerRequest {
                    return SharedDataPerRequest::deserializeFromJson(
                        SerializationTestUtil::deserializeJson($rawValue, /* asAssocArray */ true)
                    );
                }
            ),
        ];
    }
}
