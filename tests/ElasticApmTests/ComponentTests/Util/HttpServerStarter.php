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
use ElasticApmTests\Util\TestCaseBase;
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
    private $dbgServerDesc;

    protected function __construct(string $dbgServerDesc)
    {
        $this->logger = AmbientContext::loggerFactory()->loggerForClass(
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
     * @param int    $port
     * @param string $serverId
     *
     * @return array<string, string>
     */
    abstract protected function buildEnvVars(int $port, string $serverId): array;

    protected function startHttpServer(): HttpServerHandle
    {
        for ($tryCount = 0; $tryCount < self::MAX_TRIES_TO_START_SERVER; ++$tryCount) {
            $currentTryPort = self::findFreePortToListen();
            $currentTryServerId = TestInfraUtil::generateIdBasedOnTestCaseId();
            $cmdLine = $this->buildCommandLine($currentTryPort);
            $envVars = $this->buildEnvVars($currentTryPort, $currentTryServerId);

            $logger = $this->logger->inherit()->addAllContext(
                [
                    'tryCount'           => $tryCount,
                    'maxTries'           => self::MAX_TRIES_TO_START_SERVER,
                    'currentTryPort'     => $currentTryPort,
                    'currentTryServerId' => $currentTryServerId,
                    'cmdLine'            => $cmdLine,
                    'envVars'            => $envVars,
                ]
            );

            ($loggerProxy = $logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log('Starting HTTP server...');

            TestCaseBase::printMessage(
                __METHOD__,
                "Starting HTTP server. cmdLine: `$cmdLine', currentTryPort: $currentTryPort ..."
            );

            TestProcessUtil::startBackgroundProcess($cmdLine, $envVars);

            if ($this->isHttpServerRunning($currentTryPort, $currentTryServerId, $logger)) {
                ($loggerProxy = $logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
                && $loggerProxy->log('Started HTTP server');
                return new HttpServerHandle($currentTryPort, $currentTryServerId);
            }

            ($loggerProxy = $logger->ifWarningLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log('Failed to start HTTP server');
        }

        throw new RuntimeException("Failed to start HTTP server. dbgServerDesc: $this->dbgServerDesc.");
    }

    private static function findFreePortToListen(): int
    {
        return mt_rand(self::PORTS_RANGE_BEGIN, self::PORTS_RANGE_END - 1);
    }

    private function isHttpServerRunning(int $port, string $serverId, Logger $logger): bool
    {
        /** @var Throwable|null */
        $lastException = null;
        $dataPerRequest = TestInfraDataPerRequest::withServerId($serverId);
        $checkResult = (new PollingCheck(
            $this->dbgServerDesc . ' started',
            self::MAX_WAIT_SERVER_START_MICROSECONDS,
            AmbientContext::loggerFactory()
        ))->run(
            function () use ($port, $dataPerRequest, $logger, &$lastException) {
                try {
                    $response = TestHttpClientUtil::sendRequest(
                        HttpConsts::METHOD_GET,
                        (new UrlParts())->path(TestEnvBase::STATUS_CHECK_URI)->port($port),
                        $dataPerRequest
                    );
                } catch (Throwable $throwable) {
                    $lastException = $throwable;
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
            if ($lastException === null) {
                ($loggerProxy = $logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
                && $loggerProxy->log('Failed to send request to check HTTP server status');
            } else {
                ($loggerProxy = $logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
                && $loggerProxy->logThrowable($lastException, 'Failed to send request to check HTTP server status');
            }
        }

        return $checkResult;
    }
}
