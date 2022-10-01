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
use ElasticApmTests\ComponentTests\Util\AppCodeRequestParams;
use ElasticApmTests\ComponentTests\Util\AppCodeTarget;
use ElasticApmTests\ComponentTests\Util\ComponentTestCaseBase;

/**
 * @group smoke
 * @group does_not_require_external_services
 */
final class TransactionTest extends ComponentTestCaseBase
{
    public static function appCodeForTransactionWithoutSpans(): void
    {
        usleep(/* microseconds - 200 milliseconds */ 200 * 1000);
    }

    public function testTransactionWithoutSpans(): void
    {
        $testCaseHandle = $this->getTestCaseHandle();
        $appCodeHost = $testCaseHandle->ensureMainAppCodeHost();
        $appCodeHost->sendRequest(AppCodeTarget::asRouted([__CLASS__, 'appCodeForTransactionWithoutSpans']));
        $dataFromAgent = $this->waitForOneEmptyTransaction($testCaseHandle);
        $tx = $dataFromAgent->singleTransaction();
        self::assertGreaterThanOrEqual(200, $tx->duration);
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
        $testCaseHandle = $this->getTestCaseHandle();
        $appCodeHost = $testCaseHandle->ensureMainAppCodeHost();
        $appCodeHost->sendRequest(
            AppCodeTarget::asRouted([__CLASS__, 'appCodeForTransactionWithoutSpansCustomProperties']),
            function (AppCodeRequestParams $appCodeRequestParams): void {
                $appCodeRequestParams->expectedTransactionName->setValue('custom TX name');
                $appCodeRequestParams->expectedTransactionType->setValue('custom TX type');
            }
        );
        $dataFromAgent = $this->waitForOneEmptyTransaction($testCaseHandle);
        $tx = $dataFromAgent->singleTransaction();
        self::assertSame('custom TX name', $tx->name);
        self::assertSame('custom TX type', $tx->type);
        self::assertLabelsCount(5, $tx);
        self::assertSame('string_label_value', self::getLabel($tx, 'string_label_key'));
        self::assertTrue(self::getLabel($tx, 'bool_label_key'));
        self::assertSame(-987654321, self::getLabel($tx, 'int_label_key'));
        self::assertSame(1234.56789, self::getLabel($tx, 'float_label_key'));
        self::assertNull(self::getLabel($tx, 'null_label_key'));
        self::assertSame('custom TX result', $tx->result);
        self::assertEquals(100, $tx->duration);
    }
}
