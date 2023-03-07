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

use Elastic\Apm\Impl\Log\LoggableToString;
use Elastic\Apm\Impl\Log\LoggableTrait;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Util\ArrayUtil;
use Elastic\Apm\Impl\Util\JsonUtil;
use Elastic\Apm\Impl\Util\RangeUtil;
use Elastic\Apm\Impl\Util\UrlParts;
use ElasticApmTests\Util\ArrayUtilForTests;
use ElasticApmTests\Util\LogCategoryForTests;
use ElasticApmTests\Util\RandomUtilForTests;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

abstract class HttpServerStarter
{
    use LoggableTrait;

    private const PORTS_RANGE_BEGIN = 50000;
    public const PORTS_RANGE_END = 60000;

    private const MAX_WAIT_SERVER_START_MICROSECONDS = 10 * 1000 * 1000; // 10 seconds
    private const MAX_TRIES_TO_START_SERVER = 3;

    /** @var Logger */
    private $logger;

    /** @var string */
    protected $dbgServerDesc;

    protected function __construct(string $dbgServerDesc)
    {
        $this->logger = AmbientContextForTests::loggerFactory()->loggerForClass(
            LogCategoryForTests::TEST_UTIL,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        )->addContext('this', $this);

        $this->dbgServerDesc = $dbgServerDesc;
    }

    /**
     * @param int[] $ports
     *
     * @return string
     */
    abstract protected function buildCommandLine(array $ports): string;

    /**
     * @param string $spawnedProcessInternalId
     * @param int[]  $ports
     *
     * @return array<string, string>
     */
    abstract protected function buildEnvVars(string $spawnedProcessInternalId, array $ports): array;

    /**
     * @param int[] $portsInUse
     * @param int   $portsToAllocateCount
     *
     * @return HttpServerHandle
     */
    protected function startHttpServer(array $portsInUse, int $portsToAllocateCount = 1): HttpServerHandle
    {
        Assert::assertGreaterThanOrEqual(1, $portsToAllocateCount);
        /** @var ?int $lastTriedPort */
        $lastTriedPort = ArrayUtil::isEmpty($portsInUse) ? null : ArrayUtilForTests::getLastValue($portsInUse);
        for ($tryCount = 0; $tryCount < self::MAX_TRIES_TO_START_SERVER; ++$tryCount) {
            /** @var int[] $currentTryPorts */
            $currentTryPorts = [];
            self::findFreePortsToListen($portsInUse, $portsToAllocateCount, $lastTriedPort, /* out */ $currentTryPorts);
            Assert::assertSame($portsToAllocateCount, count($currentTryPorts));
            $currentTrySpawnedProcessInternalId = InfraUtilForTests::generateSpawnedProcessInternalId();
            $cmdLine = $this->buildCommandLine($currentTryPorts);
            $envVars = $this->buildEnvVars($currentTrySpawnedProcessInternalId, $currentTryPorts);

            $logger = $this->logger->inherit()->addAllContext(
                [
                    'tryCount'                           => $tryCount,
                    'maxTries'                           => self::MAX_TRIES_TO_START_SERVER,
                    'currentTryPorts'                    => $currentTryPorts,
                    'currentTrySpawnedProcessInternalId' => $currentTrySpawnedProcessInternalId,
                    'cmdLine'                            => $cmdLine,
                    'envVars'                            => $envVars,
                ]
            );

            ($loggerProxy = $logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log('Starting ' . $this->dbgServerDesc . ' HTTP server...');

            ProcessUtilForTests::startBackgroundProcess($cmdLine, $envVars);

            $pid = -1;
            if (
                $this->isHttpServerRunning(
                    $currentTrySpawnedProcessInternalId,
                    $currentTryPorts[0],
                    $logger,
                    /* ref */ $pid
                )
            ) {
                ($loggerProxy = $logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
                && $loggerProxy->log('Started ' . $this->dbgServerDesc . ' HTTP server', ['PID' => $pid]);
                return new HttpServerHandle(
                    $this->dbgServerDesc,
                    $pid,
                    $currentTrySpawnedProcessInternalId,
                    $currentTryPorts
                );
            }

            ($loggerProxy = $logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log('Failed to start HTTP server');
        }

        throw new RuntimeException('Failed to start ' . $this->dbgServerDesc . ' HTTP server');
    }

    /**
     * @param int[]  $portsInUse
     * @param ?int   $lastTriedPort
     * @param int    $portsToFindCount
     * @param int[] &$result
     *
     * @return void
     */
    private static function findFreePortsToListen(
        array $portsInUse,
        int $portsToFindCount,
        ?int $lastTriedPort,
        array &$result
    ): void {
        $result = [];
        $lastTriedPortLocal = $lastTriedPort;
        foreach (RangeUtil::generateUpTo($portsToFindCount) as $ignored) {
            $foundPort = self::findFreePortToListen($portsInUse, $lastTriedPortLocal);
            $result[] = $foundPort;
            $lastTriedPortLocal = $foundPort;
        }
    }

    /**
     * @param int[] $portsInUse
     * @param ?int  $lastTriedPort
     *
     * @return int
     */
    private static function findFreePortToListen(array $portsInUse, ?int $lastTriedPort): int
    {
        $calcNextInCircularPortRange = function (int $port): int {
            return $port === (self::PORTS_RANGE_END - 1) ? self::PORTS_RANGE_BEGIN : ($port + 1);
        };

        $portToStartSearchFrom = $lastTriedPort === null
            ? RandomUtilForTests::generateIntInRange(self::PORTS_RANGE_BEGIN, self::PORTS_RANGE_END - 1)
            : $calcNextInCircularPortRange($lastTriedPort);
        $candidate = $portToStartSearchFrom;
        while (true) {
            if (!in_array($candidate, $portsInUse)) {
                break;
            }
            $candidate = $calcNextInCircularPortRange($candidate);
            if ($candidate === $portToStartSearchFrom) {
                TestCase::fail(
                    'Could not find a free port'
                    . LoggableToString::convert(
                        [
                            'portsInUse' => $portsInUse,
                            'portToStartSearchFrom' => $portToStartSearchFrom,
                        ]
                    )
                );
            }
        }
        return $candidate;
    }

    private function isHttpServerRunning(string $spawnedProcessInternalId, int $port, Logger $logger, int &$pid): bool
    {
        /** @var ?Throwable $lastThrown */
        $lastThrown = null;
        $dataPerRequest = TestInfraDataPerRequest::withSpawnedProcessInternalId($spawnedProcessInternalId);
        $checkResult = (new PollingCheck(
            $this->dbgServerDesc . ' started',
            self::MAX_WAIT_SERVER_START_MICROSECONDS
        ))->run(
            function () use ($port, $dataPerRequest, $logger, &$lastThrown, &$pid) {
                try {
                    $response = HttpClientUtilForTests::sendRequest(
                        HttpConstantsForTests::METHOD_GET,
                        (new UrlParts())->host(HttpServerHandle::DEFAULT_HOST)
                                        ->port($port)
                                        ->path(HttpServerHandle::STATUS_CHECK_URI_PATH),
                        $dataPerRequest
                    );
                } catch (Throwable $throwable) {
                    ($loggerProxy = $logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
                    && $loggerProxy->logThrowable($throwable, 'Caught while checking if HTTP server is running');
                    $lastThrown = $throwable;
                    return false;
                }

                if ($response->getStatusCode() !== HttpConstantsForTests::STATUS_OK) {
                    ($loggerProxy = $logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
                    && $loggerProxy->log(
                        'Received non-OK status code in response to status check',
                        ['receivedStatusCode' => $response->getStatusCode()]
                    );
                    return false;
                }

                /** @var array<string, mixed> $decodedBody */
                $decodedBody = JsonUtil::decode($response->getBody()->getContents(), /* asAssocArray */ true);
                TestCase::assertArrayHasKey(HttpServerHandle::PID_KEY, $decodedBody);
                $receivedPid = $decodedBody[HttpServerHandle::PID_KEY];
                TestCase::assertIsInt($receivedPid, LoggableToString::convert(['$decodedBody' => $decodedBody]));
                $pid = $receivedPid;

                ($loggerProxy = $logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
                && $loggerProxy->log('HTTP server status is OK', ['PID' => $pid]);
                return true;
            }
        );

        if (!$checkResult) {
            if ($lastThrown === null) {
                ($loggerProxy = $logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
                && $loggerProxy->log('Failed to send request to check HTTP server status');
            } else {
                ($loggerProxy = $logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
                && $loggerProxy->logThrowable($lastThrown, 'Failed to send request to check HTTP server status');
            }
        }

        return $checkResult;
    }
}
