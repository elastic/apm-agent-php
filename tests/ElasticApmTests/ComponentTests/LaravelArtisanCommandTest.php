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

use ElasticApmTests\ComponentTests\Util\AppCodeRequestParams;
use ElasticApmTests\ComponentTests\Util\AppCodeTarget;
use ElasticApmTests\ComponentTests\Util\CliScriptAppCodeRequestParams;
use ElasticApmTests\ComponentTests\Util\ComponentTestCaseBase;

/**
 * @group smoke
 * @group does_not_require_external_services
 */
final class LaravelArtisanCommandTest extends ComponentTestCaseBase
{
    /**
     * @return iterable<array{string[]}>
     */
    private static function dataProviderForFirstArgInTransactionNameImpl(): iterable
    {
        yield [['migrate']];
        yield [[]];
        yield [['vapor:handle', 'payload']];
    }

    /**
     * @return iterable<array{string[]}>
     */
    public function dataProviderForFirstArgInTransactionName(): iterable
    {
        return self::adaptToSmoke(self::dataProviderForFirstArgInTransactionNameImpl());
    }

    /**
     * @dataProvider dataProviderForFirstArgInTransactionName
     *
     * @param string[] $artisanCommandArgs
     */
    public function testFirstArgInTransactionName(array $artisanCommandArgs): void
    {
        if (self::skipIfMainAppCodeHostIsNotCliScript()) {
            return;
        }

        $expectedTxName = 'artisan' . (empty($artisanCommandArgs) ? '' : ' ' . $artisanCommandArgs[0]);
        $testCaseHandle = $this->getTestCaseHandle();
        $appCodeHost = $testCaseHandle->ensureMainAppCodeHost();
        $appCodeHost->sendRequest(
            AppCodeTarget::asRouted([__CLASS__, 'appCodeEmpty']),
            function (AppCodeRequestParams $appCodeRequestParams) use ($expectedTxName, $artisanCommandArgs): void {
                $appCodeRequestParams->expectedTransactionName->setValue($expectedTxName);
                self::assertInstanceOf(CliScriptAppCodeRequestParams::class, $appCodeRequestParams);
                $appCodeRequestParams->scriptToRunAppCodeHost = 'artisan';
                $appCodeRequestParams->scriptToRunAppCodeHostArgs = $artisanCommandArgs;
            }
        );
        $dataFromAgent = $this->waitForOneEmptyTransaction($testCaseHandle);
        $tx = $dataFromAgent->singleTransaction();
        self::assertSame($expectedTxName, $tx->name);
    }
}
