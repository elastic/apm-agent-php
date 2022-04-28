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
use Elastic\Apm\Impl\Log\LoggableInterface;
use Elastic\Apm\Impl\Log\LoggableToString;
use Elastic\Apm\Impl\Log\LoggableTrait;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Util\ClassNameUtil;
use Elastic\Apm\Impl\Util\TimeUtil;
use ElasticApmTests\Util\ArrayUtilForTests;
use ElasticApmTests\Util\LogCategoryForTests;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class TestCaseHandle implements LoggableInterface
{
    use LoggableTrait;

    public const MAX_WAIT_TIME_DATA_FROM_AGENT_SECONDS = 3 * MockApmServer::DATA_FROM_AGENT_MAX_WAIT_TIME_SECONDS;

    /** @var Logger */
    private $logger;

    /** @var ResourcesCleanerHandle */
    protected $resourcesCleaner;

    /** @var MockApmServerHandle */
    protected $mockApmServer;

    /** @var ?AppCodeHostHandle */
    protected $mainAppCodeHost = null;

    /** @var ?HttpAppCodeHostHandle */
    protected $additionalHttpAppCodeHost = null;

    /** @var ?AppCodeInvocation */
    public $appCodeInvocation = null;

    public function __construct()
    {
        $this->logger = AmbientContextForTests::loggerFactory()->loggerForClass(
            LogCategoryForTests::TEST_UTIL,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        )->addContext('this', $this);

        $this->resourcesCleaner = self::startResourcesCleaner();
        $this->mockApmServer = self::startMockApmServer($this->resourcesCleaner);
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

    // /**
    //  * @param null|Closure(HttpAppCodeHostParams): void $setParamsFunc
    //  *
    //  * @return HttpAppCodeHostHandle
    //  */
    // public function ensureMainHttpAppCodeHost(?Closure $setParamsFunc = null): HttpAppCodeHostHandle
    // {
    //     TestCase::assertTrue(ComponentTestCaseBase::isMainAppCodeHostHttp());
    //     return $this->ensureMainHttpAppCodeHost($setParamsFunc); // @phpstan-ignore-line
    // }

    /**
     * @param null|Closure(HttpAppCodeHostParams): void $setParamsFunc
     *
     * @return HttpAppCodeHostHandle
     */
    public function ensureAdditionalHttpAppCodeHost(?Closure $setParamsFunc = null): HttpAppCodeHostHandle
    {
        if ($this->additionalHttpAppCodeHost === null) {
            $this->additionalHttpAppCodeHost = new BuiltinHttpServerAppCodeHostHandle(
                $this,
                function (HttpAppCodeHostParams $params) use ($setParamsFunc): void {
                    $this->setMandatoryOptions($params);
                    if ($setParamsFunc !== null) {
                        $setParamsFunc($params);
                    }
                },
                $this->resourcesCleaner,
                'additional' /* dbgInstanceName */
            );
        }
        return $this->additionalHttpAppCodeHost;
    }

    public function waitForDataFromAgent(
        ExpectedEventCounts $expectedEventCounts,
        bool $shouldValidate = true
    ): DataFromAgentPlusRaw {
        TestCase::assertNotNull($this->appCodeInvocation);
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
                    'expected event counts' => $expectedEventCounts,
                    'actual event counts' => $dataFromAgentAccumulator->dbgCounts(),
                    '$dataFromAgentAccumulator' => $dataFromAgentAccumulator
                ]
            )
        );

        $dataFromAgent = $dataFromAgentAccumulator->getAccumulatedData();
        if ($shouldValidate) {
            $expectations = new DataFromAgentPlusRawExpectations(
                $this->appCodeInvocation,
                ArrayUtilForTests::getLastValue($dataFromAgent->intakeApiRequests)->timeReceivedAtApmServer
            );
            DataFromAgentPlusRawValidator::validate($dataFromAgent, $expectations);
            return $dataFromAgent;
        }
        return $dataFromAgent;
    }

    private function setMandatoryOptions(AppCodeHostParams $params): void
    {
        $params->setAgentOption(OptionNames::SERVER_URL, 'http://localhost:' . $this->mockApmServer->getPort());
    }

    public function setAppCodeInvocation(AppCodeInvocation $appCodeInvocation): void
    {
        TestCase::assertNull($this->appCodeInvocation);
        $appCodeInvocation->appCodeHostsParams = [];
        if ($this->mainAppCodeHost !== null) {
            $appCodeInvocation->appCodeHostsParams[] = $this->mainAppCodeHost->appCodeHostParams;
        }
        if ($this->additionalHttpAppCodeHost !== null) {
            $appCodeInvocation->appCodeHostsParams[] = $this->additionalHttpAppCodeHost->appCodeHostParams;
        }
        $this->appCodeInvocation = $appCodeInvocation;
    }

    public function tearDown(): void
    {
        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Tearing down...');

        if ($this->mainAppCodeHost !== null) {
            $this->mainAppCodeHost->tearDown();
        }
        if ($this->additionalHttpAppCodeHost !== null) {
            $this->additionalHttpAppCodeHost->tearDown();
        }

        $this->resourcesCleaner->signalToExit();
    }

    private static function startResourcesCleaner(): ResourcesCleanerHandle
    {
        $httpServerHandle = TestInfraHttpServerStarter::startTestInfraHttpServer(
            ClassNameUtil::fqToShort(ResourcesCleaner::class) /* <- dbgProcessName */,
            'runResourcesCleaner.php' /* <- runScriptName */,
            null /* <- resourcesCleaner */
        );
        return new ResourcesCleanerHandle($httpServerHandle);
    }

    private static function startMockApmServer(ResourcesCleanerHandle $resourcesCleaner): MockApmServerHandle
    {
        $httpServerHandle = TestInfraHttpServerStarter::startTestInfraHttpServer(
            ClassNameUtil::fqToShort(MockApmServer::class) /* <- dbgProcessName */,
            'runMockApmServer.php' /* <- runScriptName */,
            $resourcesCleaner
        );
        return new MockApmServerHandle($httpServerHandle);
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
                return new BuiltinHttpServerAppCodeHostHandle(
                    $this,
                    $setParamsFunc,
                    $this->resourcesCleaner,
                    $dbgInstanceName
                );
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
        $newIntakeApiRequests = $this->mockApmServer->fetchNewData();
        $dataFromAgentAccumulator->addIntakeApiRequests($newIntakeApiRequests);
        return $dataFromAgentAccumulator->hasReachedEventCounts($expectedEventCounts);
    }
}
