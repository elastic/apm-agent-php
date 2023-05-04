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
use ElasticApmTests\ComponentTests\Util\ExpectedEventCounts;
use ElasticApmTests\ComponentTests\Util\HttpAppCodeRequestParams;
use ElasticApmTests\Util\AssertMessageStack;
use ElasticApmTests\Util\MixedMap;

/**
 * @group smoke
 * @group does_not_require_external_services
 */
final class BackendCommTest extends ComponentTestCaseBase
{
    public static function appCodeForTestNumberOfConnections(MixedMap $appCodeArgs): void
    {
        $txName = $appCodeArgs->getString('txName');
        ElasticApm::getCurrentTransaction()->setName($txName);
    }

    public function testNumberOfConnections(): void
    {
        if (self::skipIfMainAppCodeHostIsNotHttp()) {
            return;
        }
        $testCaseHandle = $this->getTestCaseHandle();
        $appCodeHost = $testCaseHandle->ensureMainAppCodeHost();
        $txNames = ['1st_test_TX', '2nd_test_TX'];
        foreach ($txNames as $txName) {
            $appCodeHost->sendRequest(
                AppCodeTarget::asRouted([__CLASS__, 'appCodeForTestNumberOfConnections']),
                function (AppCodeRequestParams $appCodeRequestParams) use ($txName): void {
                    $appCodeRequestParams->setAppCodeArgs(['txName' => $txName]);
                    $appCodeRequestParams->expectedTransactionName->setValue($txName);
                    self::assertInstanceOf(HttpAppCodeRequestParams::class, $appCodeRequestParams);
                }
            );
        }
        $txCount = count($txNames);
        $dataFromAgent = $testCaseHandle->waitForDataFromAgent((new ExpectedEventCounts())->transactions($txCount));
        AssertMessageStack::newScope(/* out */ $dbgCtx);
        $dbgCtx->add(['connections' => $dataFromAgent->getRaw()->getIntakeApiConnections()]);
        self::assertCount(1, $dataFromAgent->getRaw()->getIntakeApiConnections());
        $txIndex = 0;
        foreach ($dataFromAgent->idToTransaction as $tx) {
            self::assertSame($txNames[$txIndex], $tx->name);
            ++$txIndex;
        }
    }
}
