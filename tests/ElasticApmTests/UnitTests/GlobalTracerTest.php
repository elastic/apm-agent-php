<?php

declare(strict_types=1);

namespace ElasticApmTests\UnitTests;

use Elastic\Apm\Impl\GlobalTracerHolder;
use ElasticApmTests\UnitTests\Util\UnitTestCaseBase;

class GlobalTracerTest extends UnitTestCaseBase
{
    public function testGlobalTracerIsInitializedOnFirstAccess(): void
    {
        $this->assertNotNull(GlobalTracerHolder::get());
    }
}
