<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests\Util;

use Elastic\Apm\Impl\Util\StaticClassTrait;

final class TopLevelCodeId
{
    use StaticClassTrait;

    public const SPAN_BEGIN_END = 'SPAN_BEGIN_END';
}
