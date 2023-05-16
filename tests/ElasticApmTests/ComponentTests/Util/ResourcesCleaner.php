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
use Elastic\Apm\Impl\Util\JsonUtil;
use ElasticApmTests\Util\LogCategoryForTests;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\TimerInterface;

final class ResourcesCleaner extends TestInfraHttpServerProcessBase
{
    public const REGISTER_PROCESS_TO_TERMINATE_URI_PATH = '/register_process_to_terminate';
    public const REGISTER_FILE_TO_DELETE_URI_PATH = '/register_file_to_delete';

    public const PID_QUERY_HEADER_NAME = RequestHeadersRawSnapshotSource::HEADER_NAMES_PREFIX . 'PID';
    public const IS_TEST_SCOPED_QUERY_HEADER_NAME
        = RequestHeadersRawSnapshotSource::HEADER_NAMES_PREFIX . 'IS_TEST_SCOPED';
    public const PATH_QUERY_HEADER_NAME = RequestHeadersRawSnapshotSource::HEADER_NAMES_PREFIX . 'PATH';

    /** @var Set<string> */
    private $globalFilesToDeletePaths;

    /** @var Set<string> */
    private $testScopedFilesToDeletePaths;

    /** @var Set<int> */
    private $globalProcessesToTerminateIds;

    /** @var Set<int> */
    private $testScopedProcessesToTerminateIds;

    /** @var ?TimerInterface */
    private $parentProcessTrackingTimer = null;

    /** @var Logger */
    private $logger;

    public function __construct()
    {
        parent::__construct();

        $this->globalFilesToDeletePaths = new Set();
        $this->testScopedFilesToDeletePaths = new Set();

        $this->globalProcessesToTerminateIds = new Set();
        $this->testScopedProcessesToTerminateIds = new Set();

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

        TestCase::assertTrue(
            isset(AmbientContextForTests::testConfig()->dataPerProcess->rootProcessId), // @phpstan-ignore-line
            LoggableToString::convert(AmbientContextForTests::testConfig())
        );
    }

    /** @inheritDoc */
    protected function beforeLoopRun(): void
    {
        parent::beforeLoopRun();

        TestCase::assertNotNull($this->reactLoop);
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
        $this->cleanSpawnedProcesses(/* isTestScopedOnly */ false);
        $this->cleanFiles(/* isTestScopedOnly */ false);

        TestCase::assertNotNull($this->reactLoop);
        TestCase::assertNotNull($this->parentProcessTrackingTimer);
        $this->reactLoop->cancelTimer($this->parentProcessTrackingTimer);

        parent::exit();
    }

    private function cleanSpawnedProcesses(bool $isTestScopedOnly): void
    {
        $this->cleanSpawnedProcessesFrom(/* dbgSetDesc */ 'test scoped', $this->testScopedProcessesToTerminateIds);
        if (!$isTestScopedOnly) {
            $this->cleanSpawnedProcessesFrom(/* dbgSetDesc */ 'global', $this->globalProcessesToTerminateIds);
        }
    }

    private function cleanTestScoped(): void
    {
        $this->cleanSpawnedProcesses(/* isTestScopedOnly */ true);
        $this->cleanFiles(/* isTestScopedOnly */ true);
    }

    /**
     * @param string   $dbgSetDesc
     * @param Set<int> $processesToTerminateIds
     *
     * @return void
     */
    private function cleanSpawnedProcessesFrom(string $dbgSetDesc, Set $processesToTerminateIds): void
    {
        ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Terminating spawned processes ()...',
            ['dbgSetDesc' => $dbgSetDesc, 'processesToTerminateIds count' => $processesToTerminateIds->count()]
        );

        foreach ($processesToTerminateIds as $spawnedProcessesId) {
            if (!ProcessUtilForTests::doesProcessExist($spawnedProcessesId)) {
                ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
                && $loggerProxy->log(
                    'Spawned process does not exist anymore - no need to terminate',
                    ['spawnedProcessesId' => $spawnedProcessesId]
                );
                continue;
            }
            $hasExitedNormally = ProcessUtilForTests::terminateProcess($spawnedProcessesId);
            $hasExited = ProcessUtilForTests::waitForProcessToExit(
                'Spawn process' /* <- dbgProcessDesc */,
                $spawnedProcessesId,
                10 * 1000 * 1000 /* <- maxWaitTimeInMicroseconds - 10 seconds */
            );
            ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log(
                'Issued command to terminate spawned process',
                [
                    'spawnedProcessesId' => $spawnedProcessesId,
                    'hasExited' => $hasExited,
                    'hasExitedNormally' => $hasExitedNormally
                ]
            );
        }

        $processesToTerminateIds->clear();
    }

    private function cleanFiles(bool $isTestScopedOnly): void
    {
        $this->cleanFilesFrom(/* dbgSetDesc */ 'test scoped', $this->testScopedFilesToDeletePaths);
        if (!$isTestScopedOnly) {
            $this->cleanFilesFrom(/* dbgSetDesc */ 'global', $this->globalFilesToDeletePaths);
        }
    }

    /**
     * @param string      $dbgSetDesc
     * @param Set<string> $filesToDeletePaths
     *
     * @return void
     */
    private function cleanFilesFrom(string $dbgSetDesc, Set $filesToDeletePaths): void
    {
        ($loggerProxy = $this->logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Deleting files...',
            ['dbgSetDesc' => $dbgSetDesc, 'filesToDeletePaths count' => $filesToDeletePaths->count()]
        );

        foreach ($filesToDeletePaths as $fileToDeletePath) {
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

        $filesToDeletePaths->clear();
    }

    /** @inheritDoc */
    protected function processRequest(ServerRequestInterface $request): ?ResponseInterface
    {
        switch ($request->getUri()->getPath()) {
            case self::REGISTER_PROCESS_TO_TERMINATE_URI_PATH:
                $this->registerProcessToTerminate($request);
                break;
            case self::REGISTER_FILE_TO_DELETE_URI_PATH:
                $this->registerFileToDelete($request);
                break;
            case self::CLEAN_TEST_SCOPED_URI_PATH:
                $this->cleanTestScoped();
                break;
            default:
                return null;
        }
        return self::buildDefaultResponse();
    }

    protected function registerProcessToTerminate(ServerRequestInterface $request): void
    {
        $pid = intval(self::getRequiredRequestHeader($request, self::PID_QUERY_HEADER_NAME));
        $isTestScopedAsString = self::getRequiredRequestHeader($request, self::IS_TEST_SCOPED_QUERY_HEADER_NAME);
        $isTestScoped = JsonUtil::decode($isTestScopedAsString, /* asAssocArray */ true);
        $processesToTerminateIds
            = $isTestScoped ? $this->testScopedProcessesToTerminateIds : $this->globalProcessesToTerminateIds;
        $processesToTerminateIds->add($pid);
        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Successfully registered process to terminate',
            [
                'pid' => $pid,
                'isTestScoped' => $isTestScoped,
                'processesToTerminateIds count' => $processesToTerminateIds->count(),
            ]
        );
    }

    protected function registerFileToDelete(ServerRequestInterface $request): void
    {
        $path = self::getRequiredRequestHeader($request, self::PATH_QUERY_HEADER_NAME);
        $isTestScopedAsString = self::getRequiredRequestHeader($request, self::IS_TEST_SCOPED_QUERY_HEADER_NAME);
        $isTestScoped = JsonUtil::decode($isTestScopedAsString, /* asAssocArray */ true);
        $filesToDeletePaths = $isTestScoped ? $this->testScopedFilesToDeletePaths : $this->globalFilesToDeletePaths;
        $filesToDeletePaths->add($path);
        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Successfully registered file to delete',
            [
                'path' => $path,
                'isTestScoped' => $isTestScoped,
                'filesToDeletePaths count' => $filesToDeletePaths->count(),
            ]
        );
    }

    protected function shouldRegisterThisProcessWithResourcesCleaner(): bool
    {
        return false;
    }
}
