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
use PHPUnit\Framework\Assert;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\TimerInterface;

final class ResourcesCleaner extends TestInfraHttpServerProcessBase
{
    public const REGISTER_PROCESS_TO_TERMINATE_URI_PATH = '/register_process_to_terminate';
    public const REGISTER_FILE_TO_DELETE_URI_PATH = '/register_file_to_delete';

    public const PID_QUERY_HEADER_NAME = RequestHeadersRawSnapshotSource::HEADER_NAMES_PREFIX . 'PID';
    public const PATH_QUERY_HEADER_NAME = RequestHeadersRawSnapshotSource::HEADER_NAMES_PREFIX . 'PATH';

    /** @var Set<string> */
    private $filesToDeletePaths;

    /** @var Set<int> */
    private $processesToTerminateIds;

    /** @var ?TimerInterface */
    private $parentProcessTrackingTimer = null;

    /** @var Logger */
    private $logger;

    public function __construct()
    {
        parent::__construct();

        $this->filesToDeletePaths = new Set();
        $this->processesToTerminateIds = new Set();

        $this->logger = AmbientContextForTests::loggerFactory()->loggerForClass(
            LogCategoryForTests::TEST_UTIL,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        )->addContext('this', $this);
    }

    protected function processConfig(): void
    {
        parent::processConfig();

        Assert::assertTrue(
            isset(AmbientContextForTests::testConfig()->dataPerProcess->rootProcessId), // @phpstan-ignore-line
            LoggableToString::convert(AmbientContextForTests::testConfig())
        );
    }

    /** @inheritDoc */
    protected function beforeLoopRun(): void
    {
        Assert::assertNotNull($this->reactLoop);
        $this->parentProcessTrackingTimer = $this->reactLoop->addPeriodicTimer(
            1 /* interval in seconds */,
            function () {
                $rootProcessId = AmbientContextForTests::testConfig()->dataPerProcess->rootProcessId;
                if (!ProcessUtilForTests::doesProcessExist($rootProcessId)) {
                    ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
                    && $loggerProxy->log('Detected that parent process does not exist');
                    $this->exit();
                }
            }
        );
    }

    /** @inheritDoc */
    protected function exit(): void
    {
        $this->cleanSpawnedProcesses();
        $this->cleanFiles();

        Assert::assertNotNull($this->reactLoop);
        Assert::assertNotNull($this->parentProcessTrackingTimer);
        $this->reactLoop->cancelTimer($this->parentProcessTrackingTimer);

        parent::exit();
    }

    private function cleanSpawnedProcesses(): void
    {
        ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Terminating spawned processes...',
            ['processesToTerminateIds count' => $this->processesToTerminateIds->count()]
        );

        foreach ($this->processesToTerminateIds as $spawnedProcessesId) {
            if (!ProcessUtilForTests::doesProcessExist($spawnedProcessesId)) {
                ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
                && $loggerProxy->log(
                    'Spawned process does not exist anymore - no need to terminate',
                    ['spawnedProcessesId' => $spawnedProcessesId]
                );
                continue;
            }
            $termCmdExitCode = ProcessUtilForTests::terminateProcess($spawnedProcessesId);
            ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Issued command to terminate spawned process',
                ['spawnedProcessesId' => $spawnedProcessesId, 'termCmdExitCode' => $termCmdExitCode]
            );
        }
    }

    private function cleanFiles(): void
    {
        ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Deleting files...',
            ['filesToDeletePaths count' => $this->filesToDeletePaths->count()]
        );

        foreach ($this->filesToDeletePaths as $fileToDeletePath) {
            if (!file_exists($fileToDeletePath)) {
                ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
                && $loggerProxy->log(
                    'File does not exist - so there is nothing to delete',
                    ['fileToDeletePath' => $fileToDeletePath]
                );
                continue;
            }

            $unlinkRetVal = unlink($fileToDeletePath);
            ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Called unlink() to delete file',
                ['fileToDeletePath' => $fileToDeletePath, 'unlinkRetVal' => $unlinkRetVal]
            );
        }
    }

    /** @inheritDoc */
    protected function processRequest(ServerRequestInterface $request): ?ResponseInterface
    {
        switch ($request->getUri()->getPath()) {
            case self::REGISTER_PROCESS_TO_TERMINATE_URI_PATH:
                return $this->registerProcessToTerminate($request);
            case self::REGISTER_FILE_TO_DELETE_URI_PATH:
                return $this->registerFileToDelete($request);
            default:
                return null;
        }
    }

    protected function registerProcessToTerminate(ServerRequestInterface $request): ResponseInterface
    {
        $pid = intval(self::getRequiredRequestHeader($request, self::PID_QUERY_HEADER_NAME));
        $this->processesToTerminateIds->add($pid);
        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Successfully registered process to terminate',
            ['pid' => $pid, 'processesToTerminateIds count' => $this->processesToTerminateIds->count()]
        );

        return self::buildDefaultResponse();
    }

    protected function registerFileToDelete(ServerRequestInterface $request): ResponseInterface
    {
        $path = self::getRequiredRequestHeader($request, self::PATH_QUERY_HEADER_NAME);
        $this->filesToDeletePaths->add($path);
        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Successfully registered file to delete',
            ['path' => $path, 'filesToDeletePaths count' => $this->filesToDeletePaths->count()]
        );

        return self::buildDefaultResponse();
    }

    protected function shouldRegisterThisProcessWithResourcesCleaner(): bool
    {
        return false;
    }
}
