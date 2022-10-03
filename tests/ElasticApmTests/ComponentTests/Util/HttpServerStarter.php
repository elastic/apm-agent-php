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
use Elastic\Apm\Impl\Util\UrlParts;
use ElasticApmTests\Util\ArrayUtilForTests;
use ElasticApmTests\Util\LogCategoryForTests;
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
     * @param int $port
     *
     * @return string
     */
    abstract protected function buildCommandLine(int $port): string;

    /**
     * @param string $spawnedProcessInternalId
     * @param int    $port
     *
     * @return array<string, string>
     */
    abstract protected function buildEnvVars(string $spawnedProcessInternalId, int $port): array;

    /**
     * @param int[] $portsInUse
     *
     * @return HttpServerHandle
     */
    protected function startHttpServer(array $portsInUse): HttpServerHandle
    {
        /** @var ?int $lastTriedPort */
        $lastTriedPort = ArrayUtil::isEmpty($portsInUse) ? null : ArrayUtilForTests::getLastValue($portsInUse);
        for ($tryCount = 0; $tryCount < self::MAX_TRIES_TO_START_SERVER; ++$tryCount) {
            $currentTryPort = self::findFreePortToListen($portsInUse, $lastTriedPort);
            $lastTriedPort = $currentTryPort;
            $currentTrySpawnedProcessInternalId = InfraUtilForTests::generateSpawnedProcessInternalId();
            $cmdLine = $this->buildCommandLine($currentTryPort);
            $envVars = $this->buildEnvVars($currentTrySpawnedProcessInternalId, $currentTryPort);

            $logger = $this->logger->inherit()->addAllContext(
                [
                    'tryCount'                           => $tryCount,
                    'maxTries'                           => self::MAX_TRIES_TO_START_SERVER,
                    'currentTryPort'                     => $currentTryPort,
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
                    $currentTryPort,
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
                    $currentTryPort
                );
            }

            ($loggerProxy = $logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log('Failed to start HTTP server');
        }

        throw new RuntimeException('Failed to start ' . $this->dbgServerDesc . ' HTTP server');
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
            ? self::PORTS_RANGE_BEGIN
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
