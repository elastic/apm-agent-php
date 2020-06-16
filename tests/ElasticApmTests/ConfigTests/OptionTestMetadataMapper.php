<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ConfigTests;

use Elastic\Apm\Impl\Config\BoolOptionMetadata;
use Elastic\Apm\Impl\Config\OptionMetadataInterface;
use Elastic\Apm\Impl\Config\StringOptionMetadata;
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
            return StringOptionTestMetadata::instance();
        }

        if ($optMeta instanceof BoolOptionMetadata) {
            return BoolOptionTestMetadata::instance();
        }

        throw new RuntimeException('Unknown option metadata type: ' . DbgUtil::getType($optMeta));
    }
}
