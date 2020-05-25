<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\UnitTests;

use Elastic\Apm\Impl\GlobalTracerHolder;
use Elastic\Apm\Tests\UnitTests\Util\UnitTestCaseBase;

class GlobalTracerTest extends UnitTestCaseBase
{
    public function testGlobalTracerIsInitializedOnFirstAccess(): void
    {
        $this->assertNotNull(GlobalTracerHolder::get());
    }
}
