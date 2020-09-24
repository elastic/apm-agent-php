<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Config;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 *
 * @extends OptionMetadataBase<?int>
 */
final class NullableIntOptionMetadata extends OptionMetadataBase
{
    public function __construct(?int $defaultValue = null)
    {
        parent::__construct($defaultValue);
    }

    /**
     * @param string $rawValue
     *
     * @return mixed
     *
     * @phpstan-return int
     */
    public function parse(string $rawValue)
    {
        return IntOptionMetadata::parseValue($rawValue);
    }
}
