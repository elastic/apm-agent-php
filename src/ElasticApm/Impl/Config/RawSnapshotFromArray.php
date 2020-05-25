<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Config;

use Elastic\Apm\Impl\Util\ArrayUtil;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class RawSnapshotFromArray implements RawSnapshotInterface
{
    /** @var array<string, string> */
    private $optNameToRawValue;

    /**
     * @param array<string, string> $optNameToRawValue
     */
    public function __construct(array $optNameToRawValue)
    {
        $this->optNameToRawValue = $optNameToRawValue;
    }

    public function valueFor(string $optionName): ?string
    {
        return ArrayUtil::getValueIfKeyExistsElse($optionName, $this->optNameToRawValue, null);
    }
}
