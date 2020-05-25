<?php

declare(strict_types=1);

namespace Elastic\Apm\Impl\Config;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class CompositeRawSnapshotSource implements RawSnapshotSourceInterface
{
    /** @var array<RawSnapshotSourceInterface> */
    private $subSources;

    /**
     * @param array<RawSnapshotSourceInterface> $subSources
     */
    public function __construct(array $subSources)
    {
        $this->subSources = $subSources;
    }

    public function currentSnapshot(): RawSnapshotInterface
    {
        /** @var array<RawSnapshotInterface> */
        $subSnapshots = [];
        foreach ($this->subSources as $subSource) {
            $subSnapshots[] = $subSource->currentSnapshot();
        }
        return new CompositeRawSnapshot($subSnapshots);
    }
}
