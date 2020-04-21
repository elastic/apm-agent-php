<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests;

use Elastic\Apm\Impl\GlobalTracerHolder;

class GlobalTracerTest extends Util\TestCaseBase
{
    public function testGlobalTracerIsInitializedOnFirstAccess(): void
    {
        $this->assertNotNull(GlobalTracerHolder::get());
    }
}
