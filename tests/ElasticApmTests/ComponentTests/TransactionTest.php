<?php

/*
 * Licensed to Elasticsearch B.V. under one or more contributor
 * license agreements. See the NOTICE file distributed with
 * this work for additional information regarding copyright
 * ownership. Elasticsearch B.V. licenses this file to you under
 * the Apache License, Version 2.0 (the "License"); you may
 * not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations
 * under the License.
 */

declare(strict_types=1);

namespace ElasticApmTests\ComponentTests;

use Elastic\Apm\ElasticApm;
use Elastic\Apm\Impl\Util\ArrayUtil;
use Elastic\Apm\Impl\TransactionData;
use ElasticApmTests\ComponentTests\Util\ComponentTestCaseBase;
use ElasticApmTests\ComponentTests\Util\DataFromAgent;
use ElasticApmTests\ComponentTests\Util\HttpConsts;
use ElasticApmTests\ComponentTests\Util\TestProperties;

final class TransactionTest extends ComponentTestCaseBase
{
    public function testTransactionWithoutSpans(): void
    {
        $this->sendRequestToInstrumentedAppAndVerifyDataFromAgent(
            (new TestProperties())->withRoutedAppCode([__CLASS__, 'appCodeForTransactionWithoutSpans']),
            function (DataFromAgent $dataFromAgent): void {
                $tx = $this->verifyTransactionWithoutSpans($dataFromAgent);
                $this->assertNull($tx->context);
                $this->assertGreaterThanOrEqual(200, $tx->duration);
            }
        );
    }

    public static function appCodeForTransactionWithoutSpans(): void
    {
        usleep(/* microseconds - 200 milliseconds */ 200 * 1000);
    }

    private function verifyTransactionWithoutSpans(DataFromAgent $dataFromAgent): TransactionData
    {
        $this->assertEmpty($dataFromAgent->idToSpan());

        $tx = $dataFromAgent->singleTransaction();
        $this->assertSame(0, $tx->startedSpansCount);
        $this->assertSame(0, $tx->droppedSpansCount);
        $this->assertNull($tx->parentId);
        return $tx;
    }

    public static function appCodeForTransactionWithoutSpansCustomProperties(): void
    {
        ElasticApm::getCurrentTransaction()->setName('custom TX name');
        ElasticApm::getCurrentTransaction()->setType('custom TX type');
        ElasticApm::getCurrentTransaction()->context()->setLabel('string_label_key', 'string_label_value');
        ElasticApm::getCurrentTransaction()->context()->setLabel('bool_label_key', true);
        ElasticApm::getCurrentTransaction()->context()->setLabel('int_label_key', -987654321);
        ElasticApm::getCurrentTransaction()->context()->setLabel('float_label_key', 1234.56789);
        ElasticApm::getCurrentTransaction()->context()->setLabel('null_label_key', null);

        usleep(/* microseconds - 200 milliseconds */ 200 * 1000);

        ElasticApm::getCurrentTransaction()->setResult('custom TX result');
        ElasticApm::getCurrentTransaction()->end(/* milliseconds */ 100);
    }

    public function testTransactionWithoutSpansCustomProperties(): void
    {
        $this->sendRequestToInstrumentedAppAndVerifyDataFromAgent(
            (new TestProperties())
                ->withRoutedAppCode([__CLASS__, 'appCodeForTransactionWithoutSpansCustomProperties'])
                ->withExpectedTransactionName('custom TX name')->withTransactionType('custom TX type'),
            function (DataFromAgent $dataFromAgent): void {
                $tx = $this->verifyTransactionWithoutSpans($dataFromAgent);
                $this->assertLabelsCount(5, $tx);
                $this->assertSame('string_label_value', self::getLabel($tx, 'string_label_key'));
                $this->assertTrue(self::getLabel($tx, 'bool_label_key'));
                $this->assertSame(-987654321, self::getLabel($tx, 'int_label_key'));
                $this->assertSame(1234.56789, self::getLabel($tx, 'float_label_key'));
                $this->assertNull(self::getLabel($tx, 'null_label_key'));
                $this->assertSame('custom TX result', $tx->result);
                $this->assertEquals(100, $tx->duration);
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
                self::assertSame($this->testEnv->isHttp() ? $expectedTxResult : null, $tx->result);
            }
        );
    }
}
