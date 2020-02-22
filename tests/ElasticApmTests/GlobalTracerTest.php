<?php

declare(strict_types=1);

namespace ElasticApmTests;

use ElasticApm\Impl\GlobalTracerHolder;

class GlobalTracerTest extends Util\TestCaseBase
{
    public function testGlobalTracerIsInitializedOnFirstAccess(): void
    {
        $this->assertNotNull(GlobalTracerHolder::get());
    }
}
