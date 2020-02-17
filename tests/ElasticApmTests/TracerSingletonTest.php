<?php

declare(strict_types=1);

namespace ElasticApmTests;

use ElasticApm\TracerSingleton;

class TracerSingletonTest extends Util\TestCaseBase
{
    public function tearDown(): void
    {
        TracerSingleton::reset();
    }

    public function testTracerSingletonIsInitializedOnFirstAccess(): void
    {
        $this->assertNotNull(TracerSingleton::get());
    }
}
