<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\UnitTests\ConfigTests;

use Elastic\Apm\Impl\Config\BoolOptionMetadata;
use Elastic\Apm\Impl\Config\FloatOptionMetadata;
use Elastic\Apm\Impl\Config\IntOptionMetadata;
use Elastic\Apm\Impl\Config\NullableStringOptionMetadata;
use Elastic\Apm\Impl\Config\OptionMetadataInterface;
use Elastic\Apm\Impl\Config\StringOptionMetadata;
use Elastic\Apm\Impl\Config\TimeDurationOptionMetadata;
use Elastic\Apm\Impl\Util\DbgUtil;
use Elastic\Apm\Impl\Util\StaticClassTrait;
use RuntimeException;

final class OptionTestMetadataMapper
{
    use StaticClassTrait;

    /**
     * @param OptionMetadataInterface<mixed> $optMeta
     *
     * @return OptionTestMetadataInterface<mixed>
     */
    public static function map(OptionMetadataInterface $optMeta): OptionTestMetadataInterface
    {
        if ($optMeta instanceof StringOptionMetadata) {
            return StringOptionTestMetadata::singletonInstance();
        }

        if ($optMeta instanceof NullableStringOptionMetadata) {
            return StringOptionTestMetadata::singletonInstance();
        }

        if ($optMeta instanceof BoolOptionMetadata) {
            return BoolOptionTestMetadata::singletonInstance();
        }

        if ($optMeta instanceof IntOptionMetadata) {
            return IntOptionTestMetadata::singletonInstance();
        }

        if ($optMeta instanceof FloatOptionMetadata) {
            return new FloatOptionTestMetadata($optMeta);
        }

        if ($optMeta instanceof TimeDurationOptionMetadata) {
            return new TimeDurationOptionTestMetadata($optMeta);
        }

        throw new RuntimeException('Unknown option metadata type: ' . DbgUtil::getType($optMeta));
    }
}
