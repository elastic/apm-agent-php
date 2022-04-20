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
use ElasticApmTests\Util\LogCategoryForTests;
use ElasticApmTests\Util\TestCaseBase;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class TestCaseHandle implements LoggableInterface
{
    use LoggableTrait;

    public const MAX_WAIT_TIME_DATA_FROM_AGENT_SECONDS = 30;

    /** @var ResourcesCleanerHandle */
    protected $resourcesCleaner;

    /** @var MockApmServerHandle */
    protected $mockApmServer;

    /** @var ?AppCodeHostHandle */
    protected $mainAppCodeHost = null;

    /** @var ?HttpAppCodeHostHandle */
    protected $additionalHttpAppCodeHost = null;

    /** @var Logger */
    private $logger;

    /** @var RequestSentToAppCode[] */
    private $requestsSentToAppCode = [];

    public function __construct()
    {
        $this->logger = AmbientContext::loggerFactory()->loggerForClass(
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
                }
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
        return $this->ensureMainHttpAppCodeHost($setParamsFunc); // @phpstan-ignore-line
    }

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
                $this->resourcesCleaner
            );
        }
        return $this->additionalHttpAppCodeHost;
    }

    private function setMandatoryOptions(AppCodeHostParams $params): void
    {
        $params->setAgentOption(OptionNames::SERVER_URL, 'http://localhost:' . $this->mockApmServer->getPort());
    }

    /**
     * @param EventCounts                  $eventCounts
     * @param Closure(DataFromAgent): void $testSpecificVerifyFunc
     *
     * @return void
     */
    public function verifyDataFromAgent(EventCounts $eventCounts, Closure $testSpecificVerifyFunc): void
    {
        $this->fetchAllExpectedData($eventCounts);
        $this->verifyDataFromAgentAgainstSentRequests();
        $testSpecificVerifyFunc($this->mockApmServer->getAccumulatedData());
    }

    public function addRequestSentToAppCode(RequestSentToAppCode $requestSentToAppCode): void
    {
        $this->requestsSentToAppCode[] = $requestSentToAppCode;
    }

    private function verifyDataFromAgentAgainstSentRequests(): void
    {
        TestCaseBase::assertCount(1, $this->requestsSentToAppCode);
        // TODO: Sergey Kleyman: Implement: TestCaseHandle::verifyDataFromAgentAgainstSentRequests
        TestCaseBase::dummyAssert();
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
            ClassNameUtil::fqToShort(ResourcesCleaner::class) /* <- dbgServerDesc */,
            'runResourcesCleaner.php' /* <- runScriptName */,
            null /* <- resourcesCleaner */
        );
        return new ResourcesCleanerHandle($httpServerHandle);
    }

    private static function startMockApmServer(ResourcesCleanerHandle $resourcesCleaner): MockApmServerHandle
    {
        $httpServerHandle = TestInfraHttpServerStarter::startTestInfraHttpServer(
            ClassNameUtil::fqToShort(MockApmServer::class) /* <- dbgServerDesc */,
            'runMockApmServer.php' /* <- runScriptName */,
            $resourcesCleaner
        );
        return new MockApmServerHandle($httpServerHandle);
    }

    /**
     * @param Closure(AppCodeHostParams): void $setParamsFunc
     *
     * @return AppCodeHostHandle
     */
    private function startAppCodeHost(Closure $setParamsFunc): AppCodeHostHandle
    {
        switch (AmbientContext::testConfig()->appCodeHostKind()) {
            case AppCodeHostKind::cliScript():
                return new CliAppCodeHostHandle($this, $setParamsFunc, $this->resourcesCleaner);

            case AppCodeHostKind::builtinHttpServer():
                return new BuiltinHttpServerAppCodeHostHandle($this, $setParamsFunc, $this->resourcesCleaner);
        }

        throw new RuntimeException(
            'This point in the code should not be reached; '
            . LoggableToString::convert(['appCodeHostKind' => AmbientContext::testConfig()->appCodeHostKind()])
        );
    }

    private function fetchAllExpectedData(EventCounts $eventCounts): void
    {
        // TODO: Sergey Kleyman: Implement: TestCaseHandle::fetchAllExpectedData

        // // $this->mockApmServer->ensureLatestData()
        // // $this->mockApmServer->getAccumulatedData()
        //
        // $hasPassed = (new PollingCheck(
        //     __FUNCTION__ . ' passes',
        //     intval(TimeUtil::secondsToMicroseconds(self::MAX_WAIT_TIME_DATA_FROM_AGENT_SECONDS)),
        //     AmbientContext::loggerFactory()
        // ))->run(
        //     function () use ($eventCounts) {
        //
        //     }
        // );
    }

    // /**
    //  * @return void
    //  */
    // private function pollDataFromAgentAndVerify(EventCounts $eventCounts): void
    // {
    //     /** @var Exception|null */
    //     $lastException = null;
    //     $lastCheckedNextIntakeApiRequestIndex = $this->dataFromAgent->nextIntakeApiRequestIndexToFetch();
    //     $numberOfFailedAttempts = 0;
    //     $numberOfAttempts = 0;
    //     $hasPassed = (new PollingCheck(
    //         __FUNCTION__ . ' passes',
    //         3 * self::MAX_WAIT_TIME_DATA_FROM_AGENT_SECONDS * 1000 * 1000 /* maxWaitTimeInMicroseconds */,
    //         AmbientContext::loggerFactory()
    //     ))->run(
    //         function () use (
    //             $timeBeforeRequestToApp,
    //             $testProperties,
    //             $verifyFunc,
    //             &$lastException,
    //             &$lastCheckedNextIntakeApiRequestIndex,
    //             &$numberOfAttempts,
    //             &$numberOfFailedAttempts
    //         ) {
    //             ++$numberOfAttempts;
    //             try {
    //                 $lastCheckedIndexBeforeUpdate = $lastCheckedNextIntakeApiRequestIndex;
    //                 $this->ensureLatestDataFromMockApmServer($timeBeforeRequestToApp);
    //                 $lastCheckedNextIntakeApiRequestIndex = $this->dataFromAgent->nextIntakeApiRequestIndexToFetch();
    //                 if (
    //                     !is_null($lastException)
    //                     && ($lastCheckedIndexBeforeUpdate === $lastCheckedNextIntakeApiRequestIndex)
    //                 ) {
    //                     TestCaseBase::logAndPrintMessage(
    //                         $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__),
    //                         'No new data since the last check - there is no point in invoking $verifyFunc() again'
    //                         . '; ' . LoggableToString::convert(
    //                             [
    //                                 'lastCheckedIndexBeforeUpdate'         => $lastCheckedIndexBeforeUpdate,
    //                                 'lastCheckedNextIntakeApiRequestIndex' => $lastCheckedNextIntakeApiRequestIndex,
    //                             ]
    //                         )
    //                     );
    //
    //                     return false;
    //                 }
    //
    //                 $this->verifyDataAgainstRequest($testProperties);
    //
    //                 ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
    //                 && $loggerProxy->log('Calling $verifyFunc supplied by the test case...');
    //
    //                 $verifyFunc($this->dataFromAgent);
    //             } catch (Exception $ex) {
    //                 TestCaseBase::logAndPrintMessage(
    //                     $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__),
    //                     "Attempt $numberOfAttempts failed."
    //                     . 'Caught exception: ' . LoggableToString::convert($ex, /* prettyPrint: */ true)
    //                 );
    //
    //                 if ($ex instanceof ConnectException || $ex instanceof PhpUnitException) {
    //                     $lastException = $ex;
    //                     ++$numberOfFailedAttempts;
    //                     return false;
    //                 }
    //
    //                 /** @noinspection PhpUnhandledExceptionInspection */
    //                 throw $ex;
    //             }
    //             return true;
    //         }
    //     );
    //
    //     if (!$hasPassed) {
    //         TestCase::assertNotNull($lastException);
    //
    //         ($loggerProxy = $this->logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
    //         && $loggerProxy->log(
    //             __FUNCTION__ . ' failed',
    //             [
    //                 'last exception from verifyFunc()'     => $lastException,
    //                 'numberOfAttempts'                     => $numberOfAttempts,
    //                 'numberOfFailedAttempts'               => $numberOfFailedAttempts,
    //                 'timeBeforeRequestToApp'               => $timeBeforeRequestToApp,
    //                 'testProperties'                       => $testProperties,
    //                 'this'                                 => $this,
    //                 'lastCheckedNextIntakeApiRequestIndex' => $lastCheckedNextIntakeApiRequestIndex,
    //             ]
    //         );
    //
    //         throw $lastException;
    //     }
    // }
}
