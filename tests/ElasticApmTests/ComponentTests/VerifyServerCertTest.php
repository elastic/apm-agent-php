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
use Elastic\Apm\Impl\Config\OptionNames;
use Elastic\Apm\Impl\Util\ArrayUtil;
use ElasticApmTests\ComponentTests\Util\AppCodeHostParams;
use ElasticApmTests\ComponentTests\Util\AppCodeRequestParams;
use ElasticApmTests\ComponentTests\Util\AppCodeTarget;
use ElasticApmTests\ComponentTests\Util\ComponentTestCaseBase;

/**
 * @group smoke
 * @group does_not_require_external_services
 */
final class VerifyServerCertTest extends ComponentTestCaseBase
{
    /**
     * @return iterable<array{?bool}>
     */
    public function dataProviderForTestConfig(): iterable
    {
        yield [null];

        foreach ([false, true] as $verifyServerCertConfigVal) {
            yield [$verifyServerCertConfigVal];
        }
    }

    /**
     * @param array<string, mixed> $args
     */
    public static function appCodeForConfigTest(array $args): void
    {
        $tx = ElasticApm::getCurrentTransaction();
        $verifyServerCertConfigVal = ArrayUtil::getValueIfKeyExistsElse('verifyServerCertConfigVal', $args, null);
        /** @var ?bool $verifyServerCertConfigVal */
        $tx->context()->setLabel('verifyServerCertConfigVal', $verifyServerCertConfigVal);
    }

    /**
     * @dataProvider dataProviderForTestConfig
     *
     * @param ?bool $verifyServerCertConfigVal
     */
    public function testConfig(?bool $verifyServerCertConfigVal): void
    {
        $testCaseHandle = $this->getTestCaseHandle();
        $appCodeHost = $testCaseHandle->ensureMainAppCodeHost(
            function (AppCodeHostParams $appCodeParams) use ($verifyServerCertConfigVal): void {
                if ($verifyServerCertConfigVal !== null) {
                    $appCodeParams->setAgentOption(
                        OptionNames::VERIFY_SERVER_CERT,
                        $verifyServerCertConfigVal ? 'true' : 'false'
                    );
                }
            }
        );
        $appCodeHost->sendRequest(
            AppCodeTarget::asRouted([__CLASS__, 'appCodeForConfigTest']),
            function (AppCodeRequestParams $appCodeRequestParams) use ($verifyServerCertConfigVal): void {
                $appCodeRequestParams->setAppCodeArgs(
                    ['verifyServerCertConfigVal' => $verifyServerCertConfigVal]
                );
            }
        );
        $dataFromAgent = $this->waitForOneEmptyTransaction($testCaseHandle);
        $tx = $dataFromAgent->singleTransaction();
        self::assertLabelsCount(1, $tx);
        self::assertSame($verifyServerCertConfigVal, self::getLabel($tx, 'verifyServerCertConfigVal'));
    }
}
