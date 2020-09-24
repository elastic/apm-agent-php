<?php

/** @noinspection PhpUndefinedClassInspection */

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests\Util;

use Ds\Set;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Util\ObjectToStringBuilder;
use Elastic\Apm\Tests\Util\TestLogCategory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\LoopInterface;
use React\Http\Response;

final class ResourcesCleaner extends StatefulHttpServerProcessBase
{
    public const REGISTER_PROCESS_TO_TERMINATE_URI_PATH = '/register_process_to_terminate';
    public const CLEAN_AND_EXIT_URI_PATH = '/clean_resources_and_exit';

    public const PID_QUERY_HEADER_NAME = TestEnvBase::HEADER_NAME_PREFIX . 'PID';

    /** @var LoopInterface */
    protected $loop;

    /** @var Logger */
    private $logger;

    /** @var int */
    private $rootProcessId;

    /** @var Set<int> */
    private $processesToTerminateIds;

    public function __construct(string $runScriptFile)
    {
        parent::__construct($runScriptFile);

        $this->logger = AmbientContext::loggerFactory()->loggerForClass(
            TestLogCategory::TEST_UTIL,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        )->addContext('this', $this);

        $this->processesToTerminateIds = new Set();
    }

    protected function processConfig(): void
    {
        parent::processConfig();

        $this->rootProcessId = intval(
            self::getRequiredTestOption(
                AllComponentTestsOptionsMetadata::ROOT_PROCESS_ID_OPTION_NAME
            )
        );
    }

    protected function beforeLoopRun(LoopInterface $loop): void
    {
        $loop->addPeriodicTimer(
            1 /* interval in seconds */,
            function () {
                if (!TestProcessUtil::doesProcessExist($this->rootProcessId)) {
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
        exit(1);
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
        $pid = intval(self::getRequestHeader($request, self::PID_QUERY_HEADER_NAME));
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

    protected function toStringAddProperties(ObjectToStringBuilder $builder): void
    {
        parent::toStringAddProperties($builder);
        $builder->add('parentPid', $this->rootProcessId);
    }
}
