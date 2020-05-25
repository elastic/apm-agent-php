<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Config;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 *
 * @extends OptionMetadataBase<?string>
 */
final class NullableStringOptionMetadata extends OptionMetadataBase
{
    /** @inheritDoc */
    public function __construct(?string $defaultValue = null)
    {
        parent::__construct($defaultValue);
    }

    /**
     * @param string $rawValue
     *
     * @return mixed
     *
     * @phpstan-return string
     */
    public function parse(string $rawValue)
    {
        return $rawValue;
    }
}
