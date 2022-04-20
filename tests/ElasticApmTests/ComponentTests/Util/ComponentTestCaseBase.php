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
use Elastic\Apm\Impl\NoopTracer;
use Elastic\Apm\Impl\TransactionData;
use Elastic\Apm\Impl\Util\DbgUtil;
use Elastic\Apm\Impl\Util\ExceptionUtil;
use ElasticApmTests\Util\LogCategoryForTests;
use ElasticApmTests\Util\RandomUtilForTests;
use ElasticApmTests\Util\TestCaseBase;
use RuntimeException;

class ComponentTestCaseBase extends TestCaseBase
{
    // TODO: Sergey Kleyman: REMOVE: ComponentTestCaseBase::$testEnv
    /** @var TestEnvBase */
    protected $testEnv;

    // TODO: Sergey Kleyman: Make not-nullable
    /** @var ?TestCaseHandle */
    private $testCaseHandle = null;

    // TODO: Sergey Kleyman: REMOVE: ComponentTestCaseBase::$allConfigSetters
    /** @var AgentConfigSetter[] */
    protected $allConfigSetters;

    /**
     * @param ?string      $name
     * @param array<mixed> $data
     * @param int|string   $dataName
     */
    public function __construct(?string $name = null, array $data = [], $dataName = '')
    {
        // TODO: Sergey Kleyman: REMOVE: ComponentTestCaseBase::__construct

        parent::__construct($name, $data, $dataName); // @phpstan-ignore-line

        $this->testEnv = $this->selectTestEnv();

        $this->allConfigSetters = [new AgentConfigSetterIni(), new AgentConfigSetterEnvVars()];
    }

    protected function getTestCaseHandle(): TestCaseHandle
    {
        return $this->testCaseHandle ?? ($this->testCaseHandle = new TestCaseHandle());
    }

    /** @inheritDoc */
    public function tearDown(): void
    {
        if ($this->testCaseHandle !== null) {
            $this->testCaseHandle->tearDown();
        } else {
            $this->testEnv->tearDown();
        }
    }

    /**
     * @param TestProperties               $testProperties
     * @param Closure(DataFromAgent): void $verifyFunc
     *
     * @return void
     */
    protected function sendRequestToInstrumentedAppAndVerifyDataFromAgent(
        TestProperties $testProperties,
        Closure $verifyFunc
    ): void {
        $this->testEnv->sendRequestToInstrumentedAppAndVerifyDataFromAgent($testProperties, $verifyFunc);
    }

    private function selectTestEnv(): TestEnvBase
    {
        switch (AmbientContext::testConfig()->appCodeHostKind()) {
            case AppCodeHostKind::cliScript():
                return new CliScriptTestEnv();

            case AppCodeHostKind::builtinHttpServer():
                return new BuiltinHttpServerTestEnv();
        }

        throw new RuntimeException('This point in the code should not be reached');
    }

    public static function appCodeEmpty(): void
    {
    }

    /**
     * @return AgentConfigSetter
     */
    public function randomConfigSetter(): AgentConfigSetter
    {
        $selectedConfigSetter = RandomUtilForTests::getRandomValueFromArray($this->allConfigSetters);

        $logger = AmbientContext::loggerFactory()->loggerForClass(
            LogCategoryForTests::TEST,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        );

        ($loggerProxy = $logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Randomly selected agent config setter type: ' . DbgUtil::getType($selectedConfigSetter));

        return $selectedConfigSetter;
    }

    /**
     * @param AgentConfigSetter|null                           $configSetter
     * @param string|null                                      $configured
     * @param Closure                                          $setConfigFunc
     * @param Closure                                          $verifyFunc
     *
     * @return void
     *
     * @phpstan-param Closure(AgentConfigSetter, string): void $setConfigFunc
     * @phpstan-param Closure(DataFromAgent): void             $verifyFunc
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

    /**
     * @param array<string, mixed> $appCodeArgs
     * @param string               $appArgNameKey
     *
     * @return mixed
     */
    protected static function getMandatoryAppCodeArg(array $appCodeArgs, string $appArgNameKey)
    {
        if (!array_key_exists($appArgNameKey, $appCodeArgs)) {
            throw new RuntimeException(
                ExceptionUtil::buildMessage(
                    'Expected key is not found in app code args',
                    ['appArgNameKey' => $appArgNameKey, 'appCodeArgs' => $appCodeArgs]
                )
            );
        }
        return $appCodeArgs[$appArgNameKey];
    }

    /**
     * @param bool                 $condition
     * @param string               $messagePrefix
     * @param array<string, mixed> $context
     */
    protected static function appAssertTrue(bool $condition, string $messagePrefix, array $context = []): void
    {
        if (!$condition) {
            throw new RuntimeException(ExceptionUtil::buildMessage($messagePrefix, $context));
        }
    }

    /**
     * @param mixed                $expected
     * @param mixed                $actual
     * @param string|null          $messagePrefix
     * @param array<string, mixed> $context
     */
    protected static function appAssertSame(
        $expected,
        $actual,
        ?string $messagePrefix = null,
        array $context = []
    ): void {
        self::appAssertTrue(
            $expected == $actual,
            $messagePrefix ?? "The actual value is not the same as the expected one",
            array_merge(['expected' => $expected, 'actual' => $actual], $context)
        );
    }

    public static function isMainAppCodeHostHttp(): bool
    {
        return AmbientContext::testConfig()->appCodeHostKind()->isHttp();
    }

    protected function verifyTransactionWithoutSpans(DataFromAgent $dataFromAgent): TransactionData
    {
        $tx = $dataFromAgent->singleTransaction();
        self::assertSame(0, $tx->startedSpansCount);
        self::assertSame(0, $tx->droppedSpansCount);
        self::assertNull($tx->parentId);
        return $tx;
    }

    protected function verifyDataFromAgentOneTransaction(TestCaseHandle $testCaseHandle, Closure $verifyFunc): void
    {
        $testCaseHandle->verifyDataFromAgent((new EventCounts())->transactions(1), $verifyFunc);
    }

    protected function verifyDataFromAgentOneNoSpansTransaction(TestCaseHandle $testCaseHandle): void
    {
        $this->verifyDataFromAgentOneTransaction(
            $testCaseHandle,
            function (DataFromAgent $dataFromAgent): void {
                $this->verifyTransactionWithoutSpans($dataFromAgent);
            }
        );
    }
}
