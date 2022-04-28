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

use Elastic\Apm\Impl\Log\LoggableTrait;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Util\UrlParts;
use ElasticApmTests\Util\LogCategoryForTests;
use RuntimeException;
use Throwable;

abstract class HttpServerStarter
{
    use LoggableTrait;

    private const PORTS_RANGE_BEGIN = 50000;
    private const PORTS_RANGE_END = 60000;

    private const MAX_WAIT_SERVER_START_MICROSECONDS = 10 * 1000 * 1000; // 10 seconds
    private const MAX_TRIES_TO_START_SERVER = 3;

    /** @var Logger */
    private $logger;

    /** @var string */
    protected $dbgProcessName;

    protected function __construct(string $dbgProcessName)
    {
        $this->logger = AmbientContextForTests::loggerFactory()->loggerForClass(
            LogCategoryForTests::TEST_UTIL,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        )->addContext('this', $this);

        $this->dbgProcessName = $dbgProcessName;
    }

    /**
     * @param int $port
     *
     * @return string
     */
    abstract protected function buildCommandLine(int $port): string;

    /**
     * @param string $spawnedProcessId
     * @param int    $port
     *
     * @return array<string, string>
     */
    abstract protected function buildEnvVars(string $spawnedProcessId, int $port): array;

    protected function startHttpServer(): HttpServerHandle
    {
        for ($tryCount = 0; $tryCount < self::MAX_TRIES_TO_START_SERVER; ++$tryCount) {
            $currentTryPort = self::findFreePortToListen();
            $currentTrySpawnedProcessId = TestInfraUtil::generateIdBasedOnCurrentTestCaseId();
            $cmdLine = $this->buildCommandLine($currentTryPort);
            $envVars = $this->buildEnvVars($currentTrySpawnedProcessId, $currentTryPort);

            $logger = $this->logger->inherit()->addAllContext(
                [
                    'tryCount'                   => $tryCount,
                    'maxTries'                   => self::MAX_TRIES_TO_START_SERVER,
                    'currentTryPort'             => $currentTryPort,
                    'currentTrySpawnedProcessId' => $currentTrySpawnedProcessId,
                    'cmdLine'                    => $cmdLine,
                    'envVars'                    => $envVars,
                ]
            );

            ($loggerProxy = $logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Starting ' . $this->dbgProcessName . ' HTTP server...',
                ['cmdLine' => $cmdLine, 'currentTryPort' => $currentTryPort]
            );

            ProcessUtilForTests::startBackgroundProcess($cmdLine, $envVars);

            if ($this->isHttpServerRunning($currentTrySpawnedProcessId, $currentTryPort, $logger)) {
                ($loggerProxy = $logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
                && $loggerProxy->log('Started ' . $this->dbgProcessName . ' HTTP server');
                return new HttpServerHandle($currentTrySpawnedProcessId, $currentTryPort);
            }

            ($loggerProxy = $logger->ifWarningLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log('Failed to start HTTP server');
        }

        throw new RuntimeException("Failed to start ' . $this->dbgProcessName . ' HTTP server");
    }

    private static function findFreePortToListen(): int
    {
        return mt_rand(self::PORTS_RANGE_BEGIN, self::PORTS_RANGE_END - 1);
    }

    private function isHttpServerRunning(string $spawnedProcessId, int $port, Logger $logger): bool
    {
        /** @var ?Throwable */
        $lastThrown = null;
        $dataPerRequest = TestInfraDataPerRequest::withSpawnedProcessId($spawnedProcessId);
        $checkResult = (new PollingCheck(
            $this->dbgProcessName . ' started',
            self::MAX_WAIT_SERVER_START_MICROSECONDS
        ))->run(
            function () use ($port, $dataPerRequest, $logger, &$lastThrown) {
                try {
                    $response = HttpClientUtilForTests::sendRequest(
                        HttpConsts::METHOD_GET,
                        (new UrlParts())->host(HttpServerHandle::DEFAULT_HOST)
                                        ->port($port)
                                        ->path(HttpServerHandle::STATUS_CHECK_URI),
                        $dataPerRequest
                    );
                } catch (Throwable $throwable) {
                    ($loggerProxy = $logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
                    && $loggerProxy->logThrowable($throwable, 'Caught while checking if HTTP server is running');
                    $lastThrown = $throwable;
                    return false;
                }

                if ($response->getStatusCode() !== HttpConsts::STATUS_OK) {
                    ($loggerProxy = $logger->ifWarningLevelEnabled(__LINE__, __FUNCTION__))
                    && $loggerProxy->log(
                        'Received non-OK status code in response to status check',
                        ['receivedStatusCode' => $response->getStatusCode()]
                    );
                    return false;
                }

                ($loggerProxy = $logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
                && $loggerProxy->log('HTTP server status is OK');
                return true;
            }
        );

        if (!$checkResult) {
            if ($lastThrown === null) {
                ($loggerProxy = $logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
                && $loggerProxy->log('Failed to send request to check HTTP server status');
            } else {
                ($loggerProxy = $logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
                && $loggerProxy->logThrowable($lastThrown, 'Failed to send request to check HTTP server status');
            }
        }

        return $checkResult;
    }
}
