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
use Elastic\Apm\Impl\Config\OptionNames;
use Elastic\Apm\Impl\Log\Level as LogLevel;
use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableToString;
use Elastic\Apm\Impl\Log\LoggableTrait;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Util\ClassNameUtil;
use Elastic\Apm\Impl\Util\TimeUtil;
use ElasticApmTests\Util\LogCategoryForTests;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class TestCaseHandle implements LoggableInterface
{
    use LoggableTrait;

    public const MAX_WAIT_TIME_DATA_FROM_AGENT_SECONDS = 3 * MockApmServer::DATA_FROM_AGENT_MAX_WAIT_TIME_SECONDS;

    public const SERIALIZED_EXPECTATIONS_KEY = 'serialized_expectations';
    public const SERIALIZED_DATA_FROM_AGENT_KEY = 'serialized_data_from_agent';

    /** @var ResourcesCleanerHandle */
    private $resourcesCleaner;

    /** @var MockApmServerHandle */
    private $mockApmServer;

    /** @var AppCodeInvocation[] */
    public $appCodeInvocations = [];

    /** @var ?AppCodeHostHandle */
    protected $mainAppCodeHost = null;

    /** @var ?HttpAppCodeHostHandle */
    protected $additionalHttpAppCodeHost = null;

    /** @var Logger */
    private $logger;

    /** @var int[] */
    private $portsInUse;

    /** @var ?int */
    private $escalatedLogLevelForProdCode;

    public function __construct(?int $escalatedLogLevelForProdCode)
    {
        $this->logger = AmbientContextForTests::loggerFactory()->loggerForClass(
            LogCategoryForTests::TEST_UTIL,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        )->addContext('this', $this);

        $globalTestInfra = ComponentTestsPhpUnitExtension::getGlobalTestInfra();
        $globalTestInfra->onTestStart();
        $this->resourcesCleaner = $globalTestInfra->getResourcesCleaner();
        $this->mockApmServer = $globalTestInfra->getMockApmServer();
        $this->portsInUse = $globalTestInfra->getPortsInUse();

        $this->escalatedLogLevelForProdCode = $escalatedLogLevelForProdCode;
    }

    /**
     * @param null|Closure(AppCodeHostParams): void $setParamsFunc
     *
     * @return AppCodeHostHandle
     */
    public function ensureMainAppCodeHost(?Closure $setParamsFunc = null): AppCodeHostHandle
    {
        if ($this->mainAppCodeHost === null) {
            $this->mainAppCodeHost = $this->startAppCodeHost(
                function (AppCodeHostParams $params) use ($setParamsFunc): void {
                    $this->setMandatoryOptions($params);
                    if ($setParamsFunc !== null) {
                        $setParamsFunc($params);
                    }
                },
                'main' /* <- dbgInstanceName */
            );
        }
        return $this->mainAppCodeHost;
    }

    /**
     * @param null|Closure(HttpAppCodeHostParams): void $setParamsFunc
     *
     * @return HttpAppCodeHostHandle
     */
    public function ensureMainHttpAppCodeHost(?Closure $setParamsFunc = null): HttpAppCodeHostHandle
    {
        TestCase::assertTrue(ComponentTestCaseBase::isMainAppCodeHostHttp());
        $appCodeHostHandle = $this->ensureMainAppCodeHost(
            function (AppCodeHostParams $appCodeHostParams) use ($setParamsFunc): void {
                Assert::assertInstanceOf(HttpAppCodeHostParams::class, $appCodeHostParams);
                if ($setParamsFunc !== null) {
                    /** @noinspection PsalmAdvanceCallableParamsInspection */
                    $setParamsFunc($appCodeHostParams);
                }
            }
        );
        TestCase::assertInstanceOf(HttpAppCodeHostHandle::class, $appCodeHostHandle);
        return $appCodeHostHandle;
    }

    /**
     * @param null|Closure(HttpAppCodeHostParams): void $setParamsFunc
     *
     * @return HttpAppCodeHostHandle
     */
    public function ensureAdditionalHttpAppCodeHost(?Closure $setParamsFunc = null): HttpAppCodeHostHandle
    {
        if ($this->additionalHttpAppCodeHost === null) {
            $this->additionalHttpAppCodeHost = $this->startBuiltinHttpServerAppCodeHost(
                function (HttpAppCodeHostParams $appCodeHostParams) use ($setParamsFunc): void {
                    $this->setMandatoryOptions($appCodeHostParams);
                    if ($setParamsFunc !== null) {
                        $setParamsFunc($appCodeHostParams);
                    }
                },
                'additional' /* dbgInstanceName */
            );
        }
        return $this->additionalHttpAppCodeHost;
    }

    public function waitForDataFromAgent(
        ExpectedEventCounts $expectedEventCounts,
        bool $shouldValidate = true
    ): DataFromAgentPlusRaw {
        TestCase::assertNotEmpty($this->appCodeInvocations);
        $dataFromAgentAccumulator = new DataFromAgentPlusRawAccumulator();
        $hasPassed = (new PollingCheck(
            __FUNCTION__ . ' passes',
            intval(TimeUtil::secondsToMicroseconds(self::MAX_WAIT_TIME_DATA_FROM_AGENT_SECONDS))
        ))->run(
            function () use ($expectedEventCounts, $dataFromAgentAccumulator) {
                return $this->pollForDataFromAgent($expectedEventCounts, $dataFromAgentAccumulator);
            }
        );
        TestCase::assertTrue(
            $hasPassed,
            'The expected data from agent has not arrived.'
            . ' ' . LoggableToString::convert(
                [
                    'expected event counts'    => $expectedEventCounts,
                    'actual event counts'      => $dataFromAgentAccumulator->dbgCounts(),
                    'dataFromAgentAccumulator' => $dataFromAgentAccumulator,
                ]
            )
        );

        $dataFromAgent = $dataFromAgentAccumulator->getAccumulatedData();
        if ($shouldValidate) {
            $expectations = new DataFromAgentPlusRawExpectations(
                $this->appCodeInvocations,
                $dataFromAgent->getRaw()->getTimeAllDataReceivedAtApmServer()
            );

            $validatorClassName = ClassNameUtil::fqToShort(DataFromAgentPlusRawValidator::class);
            ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Before ' . $validatorClassName . '::validate: data that can be used for '
                . ClassNameUtil::fqToShort(DataFromAgentPlusRawValidatorDebugTest::class),
                [
                    self::SERIALIZED_EXPECTATIONS_KEY    => serialize($expectations),
                    self::SERIALIZED_DATA_FROM_AGENT_KEY => serialize($dataFromAgent),
                ]
            );
            ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Before ' . $validatorClassName . '::validate',
                ['expectations' => $expectations, 'dataFromAgent' => $dataFromAgent]
            );
            DataFromAgentPlusRawValidator::validate($dataFromAgent, $expectations);
            return $dataFromAgent;
        }
        return $dataFromAgent;
    }

    private function setMandatoryOptions(AppCodeHostParams $params): void
    {
        if ($this->escalatedLogLevelForProdCode !== null) {
            $escalatedLogLevelForProdCodeAsString = LogLevel::intToName($this->escalatedLogLevelForProdCode);
            $params->setAgentOption(OptionNames::LOG_LEVEL_SYSLOG, $escalatedLogLevelForProdCodeAsString);
        }
        $params->setAgentOption(OptionNames::SERVER_URL, 'http://localhost:' . $this->mockApmServer->getPortForAgent());
    }

    public function addAppCodeInvocation(AppCodeInvocation $appCodeInvocation): void
    {
        $appCodeInvocation->appCodeHostsParams = [];
        if ($this->mainAppCodeHost !== null) {
            $appCodeInvocation->appCodeHostsParams[] = $this->mainAppCodeHost->appCodeHostParams;
        }
        if ($this->additionalHttpAppCodeHost !== null) {
            $appCodeInvocation->appCodeHostsParams[] = $this->additionalHttpAppCodeHost->appCodeHostParams;
        }
        $this->appCodeInvocations[] = $appCodeInvocation;
    }

    /**
     * @return array<int>
     */
    public function getProdCodeLogLevels(): array
    {
        $result = [];
        /** @var ?AppCodeHostHandle $appCodeHost */
        foreach ([$this->mainAppCodeHost, $this->additionalHttpAppCodeHost] as $appCodeHost) {
            if ($appCodeHost !== null) {
                $result[] = $appCodeHost->appCodeHostParams->getEffectiveAgentConfig()->effectiveLogLevel();
            }
        }
        return $result;
    }

    public function tearDown(): void
    {
        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Tearing down...');

        ComponentTestsPhpUnitExtension::getGlobalTestInfra()->onTestEnd();
    }

    /**
     * @param int[] $ports
     *
     * @return void
     */
    private function addPortsInUse(array $ports): void
    {
        foreach ($ports as $port) {
            TestCase::assertNotContains($port, $this->portsInUse);
            $this->portsInUse[] = $port;
        }
    }

    private function startBuiltinHttpServerAppCodeHost(
        Closure $setParamsFunc,
        string $dbgInstanceName
    ): BuiltinHttpServerAppCodeHostHandle {
        $result = new BuiltinHttpServerAppCodeHostHandle(
            $this,
            $setParamsFunc,
            $this->resourcesCleaner,
            $this->portsInUse,
            $dbgInstanceName
        );
        $this->addPortsInUse($result->getHttpServerHandle()->getPorts());
        return $result;
    }

    /**
     * @param Closure(AppCodeHostParams): void $setParamsFunc
     * @param string                           $dbgInstanceName
     *
     * @return AppCodeHostHandle
     */
    private function startAppCodeHost(Closure $setParamsFunc, string $dbgInstanceName): AppCodeHostHandle
    {
        switch (AmbientContextForTests::testConfig()->appCodeHostKind()) {
            case AppCodeHostKind::cliScript():
                return new CliScriptAppCodeHostHandle(
                    $this,
                    $setParamsFunc,
                    $this->resourcesCleaner,
                    $dbgInstanceName
                );

            case AppCodeHostKind::builtinHttpServer():
                return $this->startBuiltinHttpServerAppCodeHost($setParamsFunc, $dbgInstanceName);
        }

        throw new RuntimeException(
            'This point in the code should not be reached; '
            . LoggableToString::convert(['appCodeHostKind' => AmbientContextForTests::testConfig()->appCodeHostKind()])
        );
    }

    private function pollForDataFromAgent(
        ExpectedEventCounts $expectedEventCounts,
        DataFromAgentPlusRawAccumulator $dataFromAgentAccumulator
    ): bool {
        $newReceiverEvents = $this->mockApmServer->fetchNewData();
        $dataFromAgentAccumulator->addReceiverEvents($newReceiverEvents);
        return $dataFromAgentAccumulator->hasReachedEventCounts($expectedEventCounts);
    }

    public function getResourcesClient(): ResourcesClient
    {
        return $this->resourcesCleaner->getClient();
    }
}
