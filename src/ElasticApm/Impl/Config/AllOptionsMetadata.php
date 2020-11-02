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
     * @return array<string, OptionMetadataInterface> Option name to metadata
     *
     * @phpstan-return array<string, OptionMetadataInterface<mixed>> Option name to metadata
     */
    public static function build(): array
    {
        return [
            OptionNames::ENABLED                 => new BoolOptionMetadata(/* defaultValue: */ true),
            OptionNames::ENVIRONMENT             => new NullableStringOptionMetadata(),
            OptionNames::SERVER_TIMEOUT          => new DurationOptionMetadata(
                0.0 /* minValidValueInMilliseconds */,
                null /* maxValidValueInMilliseconds */,
                DurationUnits::SECONDS /* <- defaultUnits: */,
                30 * 1000 /* <- defaultValueInMilliseconds - 30s */
            ),
            OptionNames::SERVICE_NAME            => new NullableStringOptionMetadata(),
            OptionNames::SERVICE_VERSION         => new NullableStringOptionMetadata(),
            OptionNames::TRANSACTION_MAX_SPANS   => new IntOptionMetadata(
                0 /* <- minValidValue */,
                null /* <- maxValidValue */,
                OptionDefaultValues::TRANSACTION_MAX_SPANS
            ),
            OptionNames::TRANSACTION_SAMPLE_RATE =>
                new FloatOptionMetadata(/* minValidValue */ 0.0, /* maxValidValue */ 1.0, /* defaultValue */ 1.0),
            OptionNames::VERIFY_SERVER_CERT      => new BoolOptionMetadata(/* defaultValue: */ true),
        ];
    }
}
