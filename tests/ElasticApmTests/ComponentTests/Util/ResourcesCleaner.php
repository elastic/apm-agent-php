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

use Ds\Set;
use Elastic\Apm\Impl\Log\LoggableToString;
use Elastic\Apm\Impl\Log\Logger;
use ElasticApmTests\Util\LogCategoryForTests;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\LoopInterface;
use React\Http\Response;

final class ResourcesCleaner extends StatefulHttpServerProcessBase
{
    public const REGISTER_PROCESS_TO_TERMINATE_URI_PATH = '/register_process_to_terminate';
    public const CLEAN_AND_EXIT_URI_PATH = '/clean_resources_and_exit';

    public const PID_QUERY_HEADER_NAME = RequestHeadersRawSnapshotSource::HEADER_NAMES_PREFIX . 'PID';

    /** @var LoopInterface */
    protected $loop;

    /** @var Logger */
    private $logger;

    /** @var Set<int> */
    private $processesToTerminateIds;

    public function __construct()
    {
        parent::__construct();

        $this->logger = AmbientContext::loggerFactory()->loggerForClass(
            LogCategoryForTests::TEST_UTIL,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        )->addContext('this', $this);

        $this->processesToTerminateIds = new Set();
    }

    protected function processConfig(): void
    {
        parent::processConfig();

        TestAssertUtil::assertThat(
            isset(AmbientContext::testConfig()->sharedDataPerProcess->rootProcessId),
            LoggableToString::convert(AmbientContext::testConfig())
        );
    }

    protected function beforeLoopRun(LoopInterface $loop): void
    {
        $loop->addPeriodicTimer(
            1 /* interval in seconds */,
            function () {
                $rootProcessId = AmbientContext::testConfig()->sharedDataPerProcess->rootProcessId;
                if (!TestProcessUtil::doesProcessExist($rootProcessId)) {
                    ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
                    && $loggerProxy->log('Detected that parent process does not exist');
                    $this->cleanAndExit();
                }
            }
        );
    }

    private function cleanAndExit(): void
    {
        $this->cleanSpawnedProcesses();

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Exiting...');
        exit(0);
    }

    private function cleanSpawnedProcesses(): void
    {
        if ($this->processesToTerminateIds->isEmpty()) {
            ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log('There are no spawned processes to terminate');
            return;
        }

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Terminating spawned processes...',
            ['spawnedProcessesCount' => $this->processesToTerminateIds->count()]
        );

        foreach ($this->processesToTerminateIds as $spawnedProcessesId) {
            if (!TestProcessUtil::doesProcessExist($spawnedProcessesId)) {
                ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
                && $loggerProxy->log(
                    'Spawned process does not exist anymore - no need to terminate',
                    ['spawnedProcessesId' => $spawnedProcessesId]
                );
                continue;
            }
            $termCmdExitCode = TestProcessUtil::terminateProcess($spawnedProcessesId);
            ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Issued command to terminate spawned process',
                ['spawnedProcessesId' => $spawnedProcessesId, 'termCmdExitCode' => $termCmdExitCode]
            );
        }
    }

    protected function processRequest(ServerRequestInterface $request): ResponseInterface
    {
        if ($request->getUri()->getPath() === self::REGISTER_PROCESS_TO_TERMINATE_URI_PATH) {
            return $this->registerProcessToTerminate($request);
        } elseif ($request->getUri()->getPath() === self::CLEAN_AND_EXIT_URI_PATH) {
            $this->cleanAndExit();
            // this return is not actually reachable
            return new Response();
        }

        return $this->buildErrorResponse(/* status */ 400, 'Unknown URI path: `' . $request->getRequestTarget() . '\'');
    }

    protected function registerProcessToTerminate(ServerRequestInterface $request): ResponseInterface
    {
        $pid = intval(self::getRequiredRequestHeader($request, self::PID_QUERY_HEADER_NAME));
        $this->processesToTerminateIds->add($pid);
        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Successfully registered process to terminate',
            ['pid' => $pid, 'spawnedProcessesCount' => $this->processesToTerminateIds->count()]
        );

        return new Response(HttpConsts::STATUS_OK);
    }

    protected function shouldRegisterThisProcessWithResourcesCleaner(): bool
    {
        return false;
    }
}
