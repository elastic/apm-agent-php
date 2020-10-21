<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests;

use Elastic\Apm\ElasticApm;
use Elastic\Apm\Impl\Util\ArrayUtil;
use Elastic\Apm\Tests\ComponentTests\Util\ComponentTestCaseBase;
use Elastic\Apm\Tests\ComponentTests\Util\DataFromAgent;
use Elastic\Apm\Tests\ComponentTests\Util\HttpConsts;
use Elastic\Apm\Tests\ComponentTests\Util\TestProperties;
use Elastic\Apm\TransactionDataInterface;

final class TransactionTest extends ComponentTestCaseBase
{
    public function testTransactionWithoutSpans(): void
    {
        $this->sendRequestToInstrumentedAppAndVerifyDataFromAgent(
            (new TestProperties())->withRoutedAppCode([__CLASS__, 'appCodeForTransactionWithoutSpans']),
            function (DataFromAgent $dataFromAgent): void {
                $tx = $this->verifyTransactionWithoutSpans($dataFromAgent);
                $this->assertEmpty($tx->getLabels());
                $this->assertGreaterThanOrEqual(200, $tx->getDuration());
            }
        );
    }

    public static function appCodeForTransactionWithoutSpans(): void
    {
        usleep(/* microseconds - 200 milliseconds */ 200 * 1000);
    }

    private function verifyTransactionWithoutSpans(DataFromAgent $dataFromAgent): TransactionDataInterface
    {
        $this->assertEmpty($dataFromAgent->idToSpan);

        $tx = $dataFromAgent->singleTransaction();
        $this->assertSame(0, $tx->getStartedSpansCount());
        $this->assertSame(0, $tx->getDroppedSpansCount());
        $this->assertNull($tx->getParentId());
        return $tx;
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

        ElasticApm::getCurrentTransaction()->setResult('custom TX result');
        ElasticApm::getCurrentTransaction()->end(/* milliseconds */ 100);
    }

    public function testTransactionWithoutSpansCustomProperties(): void
    {
        $this->sendRequestToInstrumentedAppAndVerifyDataFromAgent(
            (new TestProperties())
                ->withRoutedAppCode([__CLASS__, 'appCodeForTransactionWithoutSpansCustomProperties'])
                ->withTransactionName('custom TX name')->withTransactionType('custom TX type'),
            function (DataFromAgent $dataFromAgent): void {
                $tx = $this->verifyTransactionWithoutSpans($dataFromAgent);
                $this->assertCount(5, $tx->getLabels());
                $this->assertSame('string_label_value', $tx->getLabels()['string_label_key']);
                $this->assertTrue($tx->getLabels()['bool_label_key']);
                $this->assertSame(-987654321, $tx->getLabels()['int_label_key']);
                $this->assertSame(1234.56789, $tx->getLabels()['float_label_key']);
                $this->assertNull($tx->getLabels()['null_label_key']);
                $this->assertSame('custom TX result', $tx->getResult());
                $this->assertEquals(100, $tx->getDuration());
            }
        );
    }

    /**
     * @param array<string, mixed> $args
     */
    public static function appCodeForTransactionWithCustomHttpStatus(array $args): void
    {
        $customHttpStatus = ArrayUtil::getValueIfKeyExistsElse('customHttpStatus', $args, null);
        if (!is_null($customHttpStatus)) {
            http_response_code($customHttpStatus);
        }
    }

    /**
     * @return array<array<null|int|string>>
     */
    public function transactionWithCustomHttpStatusDataProvider(): array
    {
        return [
            [null, 'HTTP 2xx'],
            [200, 'HTTP 2xx'],
            [404, 'HTTP 4xx'],
            [599, 'HTTP 5xx'],
        ];
    }

    /**
     * @dataProvider transactionWithCustomHttpStatusDataProvider
     *
     * @param int|null $customHttpStatus
     * @param string   $expectedTxResult
     */
    public function testTransactionWithCustomHttpStatus(?int $customHttpStatus, string $expectedTxResult): void
    {
        $this->sendRequestToInstrumentedAppAndVerifyDataFromAgent(
            (new TestProperties())
                ->withRoutedAppCode([__CLASS__, 'appCodeForTransactionWithCustomHttpStatus'])
                ->withAppArgs(['customHttpStatus' => $customHttpStatus])
                ->withExpectedStatusCode($customHttpStatus ?? HttpConsts::STATUS_OK),
            function (DataFromAgent $dataFromAgent) use ($expectedTxResult): void {
                $tx = $this->verifyTransactionWithoutSpans($dataFromAgent);
                self::assertSame($this->testEnv->isHttp() ? $expectedTxResult : null, $tx->getResult());
            }
        );
    }
}
