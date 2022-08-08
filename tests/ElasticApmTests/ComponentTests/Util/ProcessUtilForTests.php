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
use Elastic\Apm\Impl\Util\ExceptionUtil;
use Elastic\Apm\Impl\Util\StaticClassTrait;
use ElasticApmTests\Util\LogCategoryForTests;
use ElasticApmTests\Util\TestCaseBase;
use RuntimeException;

final class ProcessUtilForTests
{
    use StaticClassTrait;

    public static function doesProcessExist(int $pid): bool
    {
        $cmd = TestOsUtil::isWindows()
            ? "tasklist /FI \"PID eq $pid\" 2>NUL | find \"$pid\" >NUL"
            : "ps -p $pid";

        exec($cmd, $cmdOutput, $cmdExitCode);
        return $cmdExitCode === 0;
    }

    public static function terminateProcess(int $pid): bool
    {
        $cmd = TestOsUtil::isWindows()
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
        self::startProcessImpl(TestOsUtil::isWindows() ? "start /B $cmd > NUL" : "$cmd > /dev/null &", $envVars);
    }

    /**
     * @param string                $cmd
     * @param array<string, string> $envVars
     */
    public static function startProcessAndWaitUntilExit(string $cmd, array $envVars): void
    {
        self::startProcessImpl(TestOsUtil::isWindows() ? "$cmd > NUL" : "$cmd > /dev/null", $envVars);
    }

    /**
     * @param string                $adaptedCmd
     * @param array<string, string> $envVars
     */
    private static function startProcessImpl(string $adaptedCmd, array $envVars): void
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
            [] /* descriptors */,
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
                'exitCode' => $exitCode,
                'adaptedCmd' => $adaptedCmd,
                'envVars' => $envVars,
            ]
        );
    }
}
