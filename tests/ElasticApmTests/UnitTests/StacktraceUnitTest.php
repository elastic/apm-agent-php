<?php

declare(strict_types=1);

namespace ElasticApmTests\UnitTests;

use Elastic\Apm\ElasticApm;
use ElasticApmTests\TestsSharedCode\StacktraceTestSharedCode;
use ElasticApmTests\UnitTests\Util\UnitTestCaseBase;

class StacktraceUnitTest extends UnitTestCaseBase
{
    public function testAllSpanCreatingApis(): void
    {
        // Act

        $tx = ElasticApm::beginCurrentTransaction('test_TX_name', 'test_TX_type');

        /** @var array<string, mixed> */
        $expectedData = [];

        $createSpanApis = StacktraceTestSharedCode::allSpanCreatingApis(/* ref */ $expectedData);
        foreach ($createSpanApis as $createSpan) {
            (new StacktraceTestSharedCode())->actPartImpl($createSpan, /* ref */ $expectedData);
        }

        $tx->end();

        // Assert

        $this->assertTransactionEquals($tx, $this->mockEventSink->singleTransaction());
        StacktraceTestSharedCode::assertPartImpl(
            count($createSpanApis),
            $expectedData,
            $this->mockEventSink->idToSpan()
        );
    }
}
