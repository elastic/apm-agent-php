<?php

declare(strict_types=1);

namespace ElasticApmTests\UnitTests;

use Elastic\Apm\ElasticApm;
use ElasticApmTests\TestsSharedCode\StacktraceTestSharedCode;
use ElasticApmTests\UnitTests\Util\TracerUnitTestCaseBase;

class StacktraceUnitTest extends TracerUnitTestCaseBase
{
    public function testAllSpanCreatingApis(): void
    {
        // Act

        $tx = ElasticApm::beginCurrentTransaction(__FUNCTION__, 'test_TX_type');

        /** @var array<string, mixed> */
        $expectedData = [];

        $createSpanApis = StacktraceTestSharedCode::allSpanCreatingApis(/* ref */ $expectedData);
        foreach ($createSpanApis as $createSpan) {
            (new StacktraceTestSharedCode())->actPartImpl($createSpan, /* ref */ $expectedData);
        }

        $tx->end();

        // Assert

        $this->assertSame(__FUNCTION__, $this->mockEventSink->singleTransaction()->name);
        StacktraceTestSharedCode::assertPartImpl(
            count($createSpanApis),
            $expectedData,
            $this->mockEventSink->idToSpan()
        );
    }
}
