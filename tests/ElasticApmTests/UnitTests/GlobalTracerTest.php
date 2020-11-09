<?php

declare(strict_types=1);

namespace ElasticApmTests\UnitTests;

use Elastic\Apm\Impl\GlobalTracerHolder;
use ElasticApmTests\UnitTests\Util\TracerUnitTestCaseBase;

class GlobalTracerTest extends TracerUnitTestCaseBase
{
    public function testGlobalTracerIsInitializedOnFirstAccess(): void
    {
        $this->assertNotNull(GlobalTracerHolder::get());
    }
}
