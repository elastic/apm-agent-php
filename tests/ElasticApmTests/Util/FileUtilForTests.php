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

namespace ElasticApmTests\Util;

use Closure;
use Elastic\Apm\Impl\Log\LoggableToString;
use Elastic\Apm\Impl\Util\ClassNameUtil;
use Elastic\Apm\Impl\Util\ExceptionUtil;
use Elastic\Apm\Impl\Util\StaticClassTrait;
use Elastic\Apm\Impl\Util\TextUtil;
use ElasticApmTests\ComponentTests\Util\AmbientContextForTests;
use ElasticApmTests\ComponentTests\Util\EnvVarUtilForTests;
use ElasticApmTests\ComponentTests\Util\OsUtilForTests;
use ElasticApmTests\ComponentTests\Util\ProcessUtilForTests;
use PHPUnit\Framework\Assert;
use RuntimeException;

/**
 * Code in this file is part of implementation internals and thus it is not covered by the backward compatibility.
 *
 * @internal
 */
final class FileUtilForTests
{
    use StaticClassTrait;

    public static function normalizePath(string $inAbsolutePath): string
    {
        $result = realpath($inAbsolutePath);
        if ($result === false) {
            throw new RuntimeException(
                ExceptionUtil::buildMessage("realpath failed", ['inAbsolutePath' => $inAbsolutePath])
            );
        }
        return $result;
    }

    /**
     * @param string                        $filePath
     * @param Closure                       $consumeLine
     *
     * @phpstan-param Closure(string): void $consumeLine
     */
    public static function readLines(string $filePath, Closure $consumeLine): void
    {
        $fileHandle = fopen($filePath, 'r');
        if ($fileHandle === false) {
            throw new RuntimeException(
                ExceptionUtil::buildMessage('Failed to open file', ['filePath' => $filePath])
            );
        }

        while (($line = fgets($fileHandle)) !== false) {
            $consumeLine($line);
        }

        if (!feof($fileHandle)) {
            throw new RuntimeException(
                ExceptionUtil::buildMessage('Failed to read from file', ['filePath' => $filePath])
            );
        }

        fclose($fileHandle);
    }

    /**
     * @param string[] $list
     *
     * @return string
     */
    public static function listToPath(array $list): string
    {
        $result = '';
        foreach ($list as $pathElement) {
            if (!TextUtil::isEmptyString($result)) {
                $result .= DIRECTORY_SEPARATOR;
            }
            $result .= $pathElement;
        }
        return $result;
    }

    public static function createTempFile(?string $dbgTempFilePurpose = null): string
    {
        $tempFileFullPath = tempnam(sys_get_temp_dir(), /* prefix */ 'ElasticApmTests_');
        $logCategory = LogCategoryForTests::TEST;
        $logger
            = AmbientContextForTests::loggerFactory()->loggerForClass($logCategory, __NAMESPACE__, __CLASS__, __FILE__);

        if ($tempFileFullPath === false) {
            ($loggerProxy = $logger->ifCriticalLevelEnabled(__LINE__, __FUNCTION__))
            && $loggerProxy->includeStackTrace()->log(
                'Failed to create a temporary file',
                ['$dbgTempFilePurpose' => $dbgTempFilePurpose]
            );
            Assert::fail(LoggableToString::convert(['$dbgTempFilePurpose' => $dbgTempFilePurpose]));
        }

        ($loggerProxy = $logger->ifTraceLevelEnabled(__LINE__, __FUNCTION__))
        && $loggerProxy->includeStackTrace()->log(
            'Created a temporary file',
            ['tempFileFullPath' => $tempFileFullPath, '$dbgTempFilePurpose' => $dbgTempFilePurpose]
        );

        return $tempFileFullPath;
    }

    private static function buildTempSubDirFullPath(string $subDirName): string
    {
        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . $subDirName;
    }

    public static function deleteTempSubDir(string $subDirName): void
    {
        $tempSubDirFullPath = self::buildTempSubDirFullPath($subDirName);
        $msg = new AssertMessageBuilder(['subDirName' => $subDirName, 'tempSubDirFullPath' => $tempSubDirFullPath]);
        if (!file_exists($tempSubDirFullPath)) {
            return;
        }
        Assert::assertTrue(is_dir($tempSubDirFullPath), $msg->s());

        $deleteDirShellCmd = OsUtilForTests::isWindows()
            ? sprintf('rd /s /q "%s"', $tempSubDirFullPath)
            : sprintf('rm -rf "%s"', $tempSubDirFullPath);

        ProcessUtilForTests::startProcessAndWaitUntilExit($deleteDirShellCmd, EnvVarUtilForTests::getAll(), /* shouldCaptureStdOutErr */ true, /* $expectedExitCode */ 0);
    }

    /**
     * @param class-string $testClassName
     * @param string       $testMethodName
     *
     * @return string
     */
    public static function buildTempSubDirName(string $testClassName, string $testMethodName): string
    {
        return 'ElasticApmTests_' . ClassNameUtil::fqToShort($testClassName) . '_' . $testMethodName . '_PID=' . getmypid();
    }

    public static function createTempSubDir(string $subDirName): string
    {
        $tempSubDirFullPath = self::buildTempSubDirFullPath($subDirName);
        $msg = new AssertMessageBuilder(['subDirName' => $subDirName, 'tempSubDirFullPath' => $tempSubDirFullPath]);
        self::deleteTempSubDir($tempSubDirFullPath);
        Assert::assertTrue(mkdir($tempSubDirFullPath), $msg->s());
        return $tempSubDirFullPath;
    }

    public static function convertPathRelativeTo(string $absPath, string $relativeToAbsPath): string
    {
        Assert::assertTrue(TextUtil::isPrefixOf($relativeToAbsPath, $absPath));
        $relPath = substr($absPath, /* offset */ strlen($relativeToAbsPath));
        foreach (['/', DIRECTORY_SEPARATOR] as $dirSeparator) {
            while (TextUtil::isPrefixOf($dirSeparator, $relPath)) {
                $relPath = substr($relPath, /* offset */ strlen($dirSeparator));
            }
        }
        return $relPath;
    }
}
