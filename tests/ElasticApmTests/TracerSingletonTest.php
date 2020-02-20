<?php

declare(strict_types=1);

namespace ElasticApmTests;

use ElasticApm\Impl\TracerSingleton;

class TracerSingletonTest extends Util\TestCaseBase
{
    public function testTracerSingletonIsInitializedOnFirstAccess(): void
    {
        $this->assertNotNull(TracerSingleton::get());
    }
}
