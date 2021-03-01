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

namespace ElasticApmTests\ComponentTests\Util;

use Closure;
use Elastic\Apm\Impl\GlobalTracerHolder;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\NoopTracer;
use ElasticApmTests\Util\TestCaseBase;
use ElasticApmTests\Util\LogCategoryForTests;
use RuntimeException;

class ComponentTestCaseBase extends TestCaseBase
{
    /** @var TestEnvBase */
    protected $testEnv;

    /** @var Logger */
    private $logger;

    /**
     * @param mixed        $name
     * @param array<mixed> $data
     * @param mixed        $dataName
     */
    public function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);

        self::init();

        $this->logger = AmbientContext::loggerFactory()->loggerForClass(
            LogCategoryForTests::TEST_UTIL,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        );

        $this->testEnv = $this->selectTestEnv();
    }

    public static function init(): void
    {
        AmbientContext::init(/* dbgProcessName */ 'Component tests');
        GlobalTracerHolder::set(NoopTracer::singletonInstance());
    }

    public function tearDown(): void
    {
        $this->testEnv->shutdown();
    }

    /**
     * @param TestProperties $testProperties
     * @param Closure        $verifyFunc
     *
     * @return void
     *
     * @phpstan-param Closure(DataFromAgent): void $verifyFunc
     */
    protected function sendRequestToInstrumentedAppAndVerifyDataFromAgent(
        TestProperties $testProperties,
        Closure $verifyFunc
    ): void {
        $this->testEnv->sendRequestToInstrumentedAppAndVerifyDataFromAgent($testProperties, $verifyFunc);
    }

    private function selectTestEnv(): TestEnvBase
    {
        switch (AmbientContext::testConfig()->appCodeHostKind) {
            case AppCodeHostKind::CLI_SCRIPT:
                return new CliScriptTestEnv();

            case AppCodeHostKind::CLI_BUILTIN_HTTP_SERVER:
                return new BuiltinHttpServerTestEnv();

            case AppCodeHostKind::EXTERNAL_HTTP_SERVER:
                return new ExternalHttpServerTestEnv();
        }

        throw new RuntimeException('This point in the code should not be reached');
    }

    public static function appCodeEmpty(): void
    {
    }

    /**
     * @return array<array<AgentConfigSetter>>
     */
    public function configSetterTestDataProvider(): iterable
    {
        return [
            // [new ConfigSetterIni()],
            [new AgentConfigSetterEnvVars()],
        ];
    }

    /**
     * @param AgentConfigSetter|null $configSetter
     * @param string|null            $configured
     * @param Closure                $setConfigFunc
     * @param Closure                $verifyFunc
     *
     * @return void
     *
     * @phpstan-param Closure(AgentConfigSetter, string): void $setConfigFunc
     * @phpstan-param Closure(DataFromAgent): void $verifyFunc
     */
    protected function configTestImpl(
        ?AgentConfigSetter $configSetter,
        ?string $configured,
        Closure $setConfigFunc,
        Closure $verifyFunc
    ): void {
        $testProperties = (new TestProperties())->withRoutedAppCode([__CLASS__, 'appCodeEmpty']);
        if (!is_null($configSetter)) {
            self::assertNotNull($configured);
            $setConfigFunc($configSetter, $configured);
            $testProperties->withAgentConfig($configSetter);
        }
        $this->sendRequestToInstrumentedAppAndVerifyDataFromAgent($testProperties, $verifyFunc);
    }
}
