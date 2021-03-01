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
use ElasticApmTests\ComponentTests\Util\AgentConfigSetter;
use ElasticApmTests\ComponentTests\Util\ComponentTestCaseBase;
use ElasticApmTests\ComponentTests\Util\DataFromAgent;
use ElasticApmTests\ComponentTests\Util\TestProperties;

final class VerifyServerCertTest extends ComponentTestCaseBase
{
    /**
     * @return iterable<array<AgentConfigSetter|bool|null>>
     */
    public function configTestDataProvider(): iterable
    {
        yield [null, null];

        foreach ($this->configSetterTestDataProvider() as $configSetter) {
            self::assertCount(1, $configSetter);
            foreach ([false, true] as $verifyServerCert) {
                yield [$configSetter[0], $verifyServerCert];
            }
        }
    }

    /**
     * @param array<string, mixed> $args
     */
    public static function appCodeForConfigTest(array $args): void
    {
        $tx = ElasticApm::getCurrentTransaction();
        $verifyServerCert = ArrayUtil::getValueIfKeyExistsElse('verifyServerCert', $args, null);
        $tx->context()->setLabel('verifyServerCert', $verifyServerCert);
    }

    /**
     * @dataProvider configTestDataProvider
     *
     * @param AgentConfigSetter|null $configSetter
     * @param bool|null              $verifyServerCert
     */
    public function testConfig(?AgentConfigSetter $configSetter, ?bool $verifyServerCert): void
    {
        $testProperties = (new TestProperties())
            ->withRoutedAppCode([__CLASS__, 'appCodeForConfigTest'])
            ->withAppArgs(['verifyServerCert' => $verifyServerCert]);
        if (is_null($verifyServerCert)) {
            self::assertNull($configSetter);
        } else {
            self::assertNotNull($configSetter);
            $configSetter->set(OptionNames::VERIFY_SERVER_CERT, $verifyServerCert ? 'true' : 'false');
            $testProperties->withAgentConfig($configSetter);
        }
        $this->sendRequestToInstrumentedAppAndVerifyDataFromAgent(
            $testProperties,
            function (DataFromAgent $dataFromAgent) use ($verifyServerCert): void {
                $tx = $dataFromAgent->singleTransaction();
                self::assertLabelsCount(1, $tx);
                self::assertSame($verifyServerCert, self::getLabel($tx, 'verifyServerCert'));
            }
        );
    }
}
