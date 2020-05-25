<?php

/** @noinspection PhpUndefinedClassInspection */

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests\Util;

use Ds\Set;
use Elastic\Apm\Impl\Log\Logger;
use Elastic\Apm\Impl\Util\DbgUtil;
use Elastic\Apm\Impl\Util\ObjectToStringBuilder;
use Elastic\Apm\Tests\Util\TestLogCategory;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\LoopInterface;
use React\Http\Response;
use RuntimeException;

final class SpawnedProcessesCleaner extends StatefulHttpServerProcessBase
{
    public const PARENT_PID_CMD_OPT_NAME = 'parent_pid';

    private const REGISTER_URI_PATH = '/terminate_process_on_parent_exit';
    public const CLEAN_AND_EXIT_URI_PATH = '/clean_spawned_processes_and_exit';

    private const PID_QUERY_HEADER_NAME = TestEnvBase::HEADER_NAME_PREFIX . 'PID';

    /** @var LoopInterface */
    protected $loop;

    /** @var Logger */
    private $logger;

    /** @var int */
    private $parentPid;

    /** @var Set<int> */
    private $spawnedProcessesIds;

    public function __construct(string $runScriptFile)
    {
        parent::__construct($runScriptFile);

        $this->logger = AmbientContext::loggerFactory()->loggerForClass(
            TestLogCategory::TEST_UTIL,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        )->addContext('this', $this);

        $this->spawnedProcessesIds = new Set();
    }

    public static function sendRequestToRegisterProcess(int $port, string $testEnvId, int $pid): void
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        $response = self::sendRequest(
            $port,
            HttpConsts::METHOD_POST,
            self::REGISTER_URI_PATH,
            [
                TestEnvBase::TEST_ENV_ID_HEADER_NAME => $testEnvId,
                self::PID_QUERY_HEADER_NAME          => strval($pid),
            ]
        );

        if ($response->getStatusCode() !== HttpConsts::STATUS_OK) {
            throw new RuntimeException(
                'Failed to register with '
                . DbgUtil::fqToShortClassName(SpawnedProcessesCleaner::class)
            );
        }
    }

    protected function cliHelpOptions(): string
    {
        return parent::cliHelpOptions()
               . ' --' . self::PARENT_PID_CMD_OPT_NAME . /** @lang text */ '=<parent PID>';
    }

    protected function parseArgs(): void
    {
        parent::parseArgs();

        $longOpts = [];

        // --parent-pid=98765 - required value
        $longOpts[] = SpawnedProcessesCleaner::PARENT_PID_CMD_OPT_NAME . ':';

        $parsedCliOptions = getopt(/* shortOpts */ '', $longOpts);

        $parentPidAsString = $this->checkRequiredCliOption(
            SpawnedProcessesCleaner::PARENT_PID_CMD_OPT_NAME,
            $parsedCliOptions
        );
        $this->parentPid = intval($parentPidAsString);
    }

    protected function beforeLoopRun(LoopInterface $loop): void
    {
        $loop->addPeriodicTimer(
            1 /* interval in seconds */,
            function () {
                if (!TestProcessUtil::doesProcessExist($this->parentPid)) {
                    ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
                    && $loggerProxy->log('Detected that parent process does not exist');
                    $this->cleanAndExit();
                }
            }
        );
    }

    public static function sendRequestToCleanAndExit(int $port, string $testEnvId): void
    {
        try {
            self::sendRequest(
                $port,
                HttpConsts::METHOD_POST,
                self::CLEAN_AND_EXIT_URI_PATH,
                [TestEnvBase::TEST_ENV_ID_HEADER_NAME => $testEnvId]
            );
        } /** @noinspection PhpUndefinedClassInspection */ catch (GuzzleException $ex) {
            // clean-and-exit request is expected to throw
            // because SpawnedProcessesCleaner process exits before responding
        }
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
        if ($this->spawnedProcessesIds->isEmpty()) {
            ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log('There are no spawned processes to terminate');
            return;
        }

        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Terminating spawned processes...',
            ['spawnedProcessesCount' => $this->spawnedProcessesIds->count()]
        );

        foreach ($this->spawnedProcessesIds as $spawnedProcessesId) {
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
        if ($request->getUri()->getPath() === self::REGISTER_URI_PATH) {
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
        $this->spawnedProcessesIds->add($pid);
        ($loggerProxy = $this->logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Successfully registered process to terminate',
            ['pid' => $pid, 'spawnedProcessesCount' => $this->spawnedProcessesIds->count()]
        );

        return new Response(HttpConsts::STATUS_OK);
    }

    protected function shouldRegisterWithSpawnedProcessesCleaner(): bool
    {
        return false;
    }

    protected function toStringAddProperties(ObjectToStringBuilder $builder): void
    {
        parent::toStringAddProperties($builder);
        $builder->add('parentPid', $this->parentPid);
    }
}
