<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests;

use Elastic\Apm\ElasticApm;
use Elastic\Apm\Tests\ComponentTests\Util\ComponentTestCaseBase;
use Elastic\Apm\Tests\ComponentTests\Util\DataFromAgent;
use Elastic\Apm\Tests\ComponentTests\Util\TestProperties;

final class TransactionTest extends ComponentTestCaseBase
{
    public function testTransactionWithoutSpans(): void
    {
        $this->sendRequestToInstrumentedAppAndVerifyDataFromAgent(
            [__CLASS__, 'appCodeForTransactionWithoutSpans'],
            function (DataFromAgent $dataFromAgent): void {
                $this->verifyTransactionWithoutSpans($dataFromAgent);
                $tx = $dataFromAgent->singleTransaction();
                $this->assertEmpty($tx->getLabels());
                $this->assertGreaterThanOrEqual(200, $tx->getDuration());
            }
        );
    }

    public static function appCodeForTransactionWithoutSpans(): void
    {
        usleep(/* microseconds - 200 milliseconds */ 200 * 1000);
    }

    public function verifyTransactionWithoutSpans(DataFromAgent $dataFromAgent): void
    {
        $this->assertEmpty($dataFromAgent->idToSpan());

        $tx = $dataFromAgent->singleTransaction();
        $this->assertSame(0, $tx->getStartedSpansCount());
        $this->assertSame(0, $tx->getDroppedSpansCount());
        $this->assertNull($tx->getParentId());
    }

    public function testTransactionWithoutSpansCustomProperties(): void
    {
        $this->sendRequestToInstrumentedAppAndVerifyDataFromAgentEx(
            (new TestProperties([__CLASS__, 'appCodeForTransactionWithoutSpansCustomProperties']))
                ->withTransactionName('custom TX name')->withTransactionType('custom TX type'),
            function (DataFromAgent $dataFromAgent): void {
                $this->verifyTransactionWithoutSpans($dataFromAgent);
                $tx = $dataFromAgent->singleTransaction();
                $this->assertCount(5, $tx->getLabels());
                $this->assertSame('string_label_value', $tx->getLabels()['string_label_key']);
                $this->assertTrue($tx->getLabels()['bool_label_key']);
                $this->assertSame(-987654321, $tx->getLabels()['int_label_key']);
                $this->assertSame(1234.56789, $tx->getLabels()['float_label_key']);
                $this->assertNull($tx->getLabels()['null_label_key']);
                $this->assertEquals(100, $tx->getDuration());
            }
        );
    }

    public static function appCodeForTransactionWithoutSpansCustomProperties(): void
    {
        ElasticApm::getCurrentTransaction()->setName('custom TX name');
        ElasticApm::getCurrentTransaction()->setType('custom TX type');
        ElasticApm::getCurrentTransaction()->setLabel('string_label_key', 'string_label_value');
        ElasticApm::getCurrentTransaction()->setLabel('bool_label_key', true);
        ElasticApm::getCurrentTransaction()->setLabel('int_label_key', -987654321);
        ElasticApm::getCurrentTransaction()->setLabel('float_label_key', 1234.56789);
        ElasticApm::getCurrentTransaction()->setLabel('null_label_key', null);
        usleep(/* microseconds - 200 milliseconds */ 200 * 1000);
        ElasticApm::getCurrentTransaction()->end(/* milliseconds */ 100);
    }
}
