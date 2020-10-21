<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\UnitTests;

use Elastic\Apm\ElasticApm;
use Elastic\Apm\Tests\TestsSharedCode\StacktraceTestSharedCode;
use Elastic\Apm\Tests\UnitTests\Util\UnitTestCaseBase;

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

        $this->assertTransactionEquals($tx, $this->mockEventSink->getSingleTransaction());
        StacktraceTestSharedCode::assertPartImpl(
            count($createSpanApis),
            $expectedData,
            $this->mockEventSink->getIdToSpan()
        );
    }
}
