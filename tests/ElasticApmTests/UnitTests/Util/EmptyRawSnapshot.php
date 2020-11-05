<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\UnitTests\Util;

use Elastic\Apm\Impl\Config\RawSnapshotInterface;
use Elastic\Apm\Impl\Util\SingletonInstanceTrait;

final class EmptyRawSnapshot implements RawSnapshotInterface
{
    use SingletonInstanceTrait;

    public function valueFor(string $optionName): ?string
    {
        return null;
    }
}
