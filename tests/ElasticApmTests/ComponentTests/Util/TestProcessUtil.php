<?php

declare(strict_types=1);

namespace Elastic\Apm\Tests\ComponentTests\Util;

use Elastic\Apm\Impl\Util\StaticClassTrait;
use RuntimeException;

final class TestProcessUtil
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

        exec($cmd, $cmdOutput, $cmdExitCode);
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
    public static function runProcessAndWaitUntilExit(string $cmd, array $envVars): void
    {
        self::startProcessImpl(TestOsUtil::isWindows() ? "$cmd > NUL" : "$cmd > /dev/null", $envVars);
    }

    /**
     * @param string                $adaptedCmd
     * @param array<string, string> $envVars
     */
    public static function startProcessImpl(string $adaptedCmd, array $envVars): void
    {
        $pipes = [];
        $openedProc = proc_open(
            $adaptedCmd,
            [] /* descriptors */,
            $pipes /* ref */,
            null /* cwd */,
            $envVars
        );
        if ($openedProc === false) {
            throw new RuntimeException("Failed to start process. adaptedCmd: `$adaptedCmd'");
        }
        proc_close($openedProc);
    }
}
