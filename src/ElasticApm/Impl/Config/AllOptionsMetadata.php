<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Config;

use Elastic\Apm\Impl\Util\StaticClassTrait;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class AllOptionsMetadata
{
    use StaticClassTrait;

    /**
     * @return array<string, OptionMetadataInterface<mixed>> Option name to metadata
     */
    public static function build(): array
    {
        return [
            OptionNames::ENABLED      => new BoolOptionMetadata(/* defaultValue: */ true),
            OptionNames::SERVICE_NAME => new NullableStringOptionMetadata(),
            OptionNames::SERVICE_VERSION => new NullableStringOptionMetadata(),
        ];
    }
}
