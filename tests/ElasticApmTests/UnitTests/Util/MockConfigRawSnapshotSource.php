<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\UnitTests\Util;

use Elastic\Apm\Impl\Config\RawSnapshotFromArray;
use Elastic\Apm\Impl\Config\RawSnapshotInterface;
use Elastic\Apm\Impl\Config\RawSnapshotSourceInterface as ConfigRawSnapshotSourceInterface;

class MockConfigRawSnapshotSource implements ConfigRawSnapshotSourceInterface
{
    /** @var array<string, string> */
    private $optNameToRawValue = [];

    public function set(string $optName, string $optVal): self
    {
        $this->optNameToRawValue[$optName] = $optVal;
        return $this;
    }

    public function currentSnapshot(array $optionNameToMeta): RawSnapshotInterface
    {
        return new RawSnapshotFromArray($this->optNameToRawValue);
    }
}
