<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Config;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class CompositeRawSnapshot implements RawSnapshotInterface
{
    /** @var array<RawSnapshotInterface> */
    private $subSnapshots;

    /**
     * @param array<RawSnapshotInterface> $subSnapshots
     */
    public function __construct(array $subSnapshots)
    {
        $this->subSnapshots = $subSnapshots;
    }

    public function valueFor(string $optionName): ?string
    {
        foreach ($this->subSnapshots as $subSnapshot) {
            if (!is_null($value = $subSnapshot->valueFor($optionName))) {
                return $value;
            }
        }
        return null;
    }
}
