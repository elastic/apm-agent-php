<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests\AutoInstrument;

use Elastic\Apm\Tests\Util\TestCaseBase;

class AutoInstrumentTestCaseBase extends TestCaseBase
{
    public function setUp(): void
    {
        // if (!extension_loaded('elasticapm')) {
        //     $this->markTestSkipped('This test is skipped because elasticapm extension is not loaded.');
        // }

        // No need to setup tracer
    }
}
