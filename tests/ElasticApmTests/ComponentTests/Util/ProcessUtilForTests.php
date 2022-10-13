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

use Elastic\Apm\Impl\Log\Level as LogLevel;
use Elastic\Apm\Impl\Log\LoggableToString;
use Elastic\Apm\Impl\Util\ExceptionUtil;
use Elastic\Apm\Impl\Util\StaticClassTrait;
use ElasticApmTests\Util\LogCategoryForTests;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ProcessUtilForTests
{
    use StaticClassTrait;

    private const PROC_OPEN_DESCRIPTOR_FILE_TYPE = 'file';

    public static function doesProcessExist(int $pid): bool
    {
        $cmd = OsUtilForTests::isWindows()
            ? "tasklist /FI \"PID eq $pid\" 2>NUL | find \"$pid\" >NUL"
            : "ps -p $pid";

        exec($cmd, $cmdOutput, $cmdExitCode);
        return $cmdExitCode === 0;
    }

    public static function waitForProcessToExit(string $dbgProcessDesc, int $pid, int $maxWaitTimeInMicroseconds): bool
    {
        return (new PollingCheck(
            $dbgProcessDesc . ' process (PID: ' . $pid . ') exited' /* <- dbgDesc */,
            $maxWaitTimeInMicroseconds
        ))->run(
            function () use ($pid) {
                return !self::doesProcessExist($pid);
            }
        );
    }

    public static function terminateProcess(int $pid): bool
    {
        $cmd = OsUtilForTests::isWindows()
            ? "taskkill /F /PID $pid >NUL"
            : "kill $pid > /dev/null";

        exec($cmd, /* ref */ $cmdOutput, /* ref */ $cmdExitCode);
        return $cmdExitCode === 0;
    }

    /**
     * @param string                $cmd
     * @param array<string, string> $envVars
     */
    public static function startBackgroundProcess(string $cmd, array $envVars): void
    {
        self::startProcessImpl(
            OsUtilForTests::isWindows() ? "start /B $cmd > NUL" : "$cmd > /dev/null &",
            $envVars,
            [] /* <- descriptorSpec: */
        );
    }

    /**
     * @param string                $cmd
     * @param array<string, string> $envVars
     * @param bool                  $shouldCaptureStdOutErr
     *
     * @return int
     */
    public static function startProcessAndWaitUntilExit(
        string $cmd,
        array $envVars,
        bool $shouldCaptureStdOutErr = false,
        ?int $expectedExitCode = null
    ): int {
        $descriptorSpec = [];
        $tempOutputFilePath = '';
        if ($shouldCaptureStdOutErr) {
            $tempOutputFilePath = tempnam(sys_get_temp_dir(), '');
            $tempOutputFilePath .= '_' . str_replace('\\', '_', __CLASS__) . '_stdout+stderr.txt';
            if (file_exists($tempOutputFilePath)) {
                TestCase::assertTrue(unlink($tempOutputFilePath));
            }
            $descriptorSpec[1] = [self::PROC_OPEN_DESCRIPTOR_FILE_TYPE, $tempOutputFilePath, "w"]; // 1 - stdout
            $descriptorSpec[2] = [self::PROC_OPEN_DESCRIPTOR_FILE_TYPE, $tempOutputFilePath, "w"]; // 2 - stderr
        }

        $hasReturnedExitCode = false;
        $exitCode = -1;
        try {
            $exitCode = self::startProcessImpl($cmd, $envVars, $descriptorSpec);
            $hasReturnedExitCode = true;
        } finally {
            $logger = AmbientContextForTests::loggerFactory()->loggerForClass(
                LogCategoryForTests::TEST_UTIL,
                __NAMESPACE__,
                __CLASS__,
                __FILE__
            );
            $logLevel = $hasReturnedExitCode ? LogLevel::DEBUG : LogLevel::ERROR;
            $logCtx = [];
            if ($hasReturnedExitCode) {
                $logCtx['exit code'] = $exitCode;
            }
            if ($shouldCaptureStdOutErr) {
                $logCtx['file for stdout + stderr'] = $tempOutputFilePath;
                if (file_exists($tempOutputFilePath)) {
                    $logCtx['stdout + stderr'] = file_get_contents($tempOutputFilePath);
                }
            }

            ($loggerProxy = $logger->ifLevelEnabled($logLevel, __LINE__, __FUNCTION__))
            && $loggerProxy->log($cmd . ' exited', $logCtx);

            if ($expectedExitCode !== null && $hasReturnedExitCode) {
                TestCase::assertSame($expectedExitCode, $exitCode, LoggableToString::convert($logCtx));
            }
        }

        return $exitCode;
    }

    /**
     * @param string                               $adaptedCmd
     * @param array<string, string>                $envVars
     * @param array<array{string, string, string}> $descriptorSpec
     *
     * @return int
     */
    private static function startProcessImpl(string $adaptedCmd, array $envVars, array $descriptorSpec): int
    {
        $logger = AmbientContextForTests::loggerFactory()->loggerForClass(
            LogCategoryForTests::TEST_UTIL,
            __NAMESPACE__,
            __CLASS__,
            __FILE__
        )->addAllContext(['adaptedCmd' => $adaptedCmd, 'envVars' => $envVars]);

        ($loggerProxy = $logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log('Starting process...', ['adaptedCmd' => $adaptedCmd]);

        $pipes = [];
        $openedProc = proc_open(
            $adaptedCmd,
            $descriptorSpec,
            $pipes /* ref */,
            null /* cwd */,
            $envVars
        );
        if ($openedProc === false) {
            ($loggerProxy = $logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log('Failed to start process', ['adaptedCmd' => $adaptedCmd]);
            throw new RuntimeException("Failed to start process. adaptedCmd: `$adaptedCmd'");
        }

        $newProcessInfo = proc_get_status($openedProc);

        $exitCode = proc_close($openedProc);
        if ($exitCode === SpawnedProcessBase::FAILURE_PROCESS_EXIT_CODE) {
            ($loggerProxy = $logger->ifErrorLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->log('Process exited with the failure exit code', ['exitCode' => $exitCode]);
            throw new RuntimeException(
                ExceptionUtil::buildMessage(
                    'Process exited with the failure exit code',
                    ['exitCode' => $exitCode, 'adaptedCmd' => $adaptedCmd, 'envVars' => $envVars]
                )
            );
        }

        ($loggerProxy = $logger->ifDebugLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->log(
            'Process started',
            [
                'newProcessInfo' => $newProcessInfo,
                'exitCode'       => $exitCode,
                'adaptedCmd'     => $adaptedCmd,
                'envVars'        => $envVars,
            ]
        );

        return $exitCode;
    }
}
