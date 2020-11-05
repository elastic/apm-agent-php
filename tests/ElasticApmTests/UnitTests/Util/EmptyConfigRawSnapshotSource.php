<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\UnitTests\Util;

use Elastic\Apm\Impl\Config\RawSnapshotFromArray;
use Elastic\Apm\Impl\Config\RawSnapshotInterface;
use Elastic\Apm\Impl\Config\RawSnapshotSourceInterface as ConfigRawSnapshotSourceInterface;

final class EmptyConfigRawSnapshotSource implements ConfigRawSnapshotSourceInterface
{
    public function currentSnapshot(array $optionNameToMeta): RawSnapshotInterface
    {
        return EmptyRawSnapshot::singletonInstance();
    }
}
